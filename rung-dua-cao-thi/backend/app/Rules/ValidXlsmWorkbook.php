<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;
use ZipArchive;

class ValidXlsmWorkbook implements ValidationRule
{
    private const CONTENT_TYPES_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const OFFICE_RELATIONSHIPS_NAMESPACE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const DOCUMENT_RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const WORKBOOK_CONTENT_TYPE = 'application/vnd.ms-excel.sheet.macroEnabled.main+xml';

    private const WORKSHEET_RELATIONSHIP_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet';

    private const MAX_ARCHIVE_ENTRIES = 10000;

    private const MAX_UNCOMPRESSED_BYTES = 104857600;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/vnd.ms-excel.sheet.macroEnabled.12',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The uploaded file is not valid.');

            return;
        }

        if (strtolower(pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION)) !== 'xlsm') {
            $fail('Only files with the .xlsm extension are accepted.');

            return;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP extension zip is required to inspect XLSM uploads.');
        }

        if (! class_exists(\finfo::class)) {
            throw new RuntimeException('PHP fileinfo extension is required to inspect XLSM uploads.');
        }

        try {
            $valid = $this->isValidMacroEnabledWorkbook($value->getRealPath());
        } catch (Throwable) {
            $valid = false;
        }

        if (! $valid) {
            $fail('The file must be a valid macro-enabled workbook containing the NHAT KY DOAN sheet.');
        }
    }

    private function isValidMacroEnabledWorkbook(string|false $path): bool
    {
        if (! is_string($path) || ! is_file($path)) {
            return false;
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($mimeType) || ! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return false;
        }

        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        try {
            if ($archive->numFiles < 4 || $archive->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                return false;
            }

            $archiveBytes = 0;
            $entryNames = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry) || ! isset($entry['name'], $entry['size'])) {
                    return false;
                }

                $name = (string) $entry['name'];
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\')
                    || in_array('..', explode('/', $name), true) || isset($entryNames[$name])) {
                    return false;
                }

                $entryNames[$name] = true;
                $archiveBytes += (int) $entry['size'];
                if ($archiveBytes > self::MAX_UNCOMPRESSED_BYTES) {
                    return false;
                }
            }

            foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $requiredEntry) {
                if (! isset($entryNames[$requiredEntry])) {
                    return false;
                }
            }

            $contentTypes = $this->loadXml($archive->getFromName('[Content_Types].xml'));
            $packageRelationships = $this->loadXml($archive->getFromName('_rels/.rels'));
            $workbook = $this->loadXml($archive->getFromName('xl/workbook.xml'));
            $workbookRelationships = $this->loadXml($archive->getFromName('xl/_rels/workbook.xml.rels'));

            if ($contentTypes === null || $packageRelationships === null || $workbook === null || $workbookRelationships === null) {
                return false;
            }

            if (! $this->hasMacroEnabledWorkbookContentType($contentTypes)
                || ! $this->hasWorkbookRelationship($packageRelationships)
                || ! $this->hasJournalWorksheet($workbook, $workbookRelationships, $entryNames)) {
                return false;
            }

            return true;
        } finally {
            $archive->close();
        }
    }

    private function loadXml(string|false $contents): ?\DOMDocument
    {
        if (! is_string($contents) || stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            return null;
        }

        $document = new \DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;

        return $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS) ? $document : null;
    }

    private function hasMacroEnabledWorkbookContentType(\DOMDocument $document): bool
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('ct', self::CONTENT_TYPES_NAMESPACE);
        $override = $xpath->query('/ct:Types/ct:Override[@PartName="/xl/workbook.xml"]')->item(0);

        return $override instanceof \DOMElement && $override->getAttribute('ContentType') === self::WORKBOOK_CONTENT_TYPE;
    }

    private function hasWorkbookRelationship(\DOMDocument $document): bool
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('rel', self::OFFICE_RELATIONSHIPS_NAMESPACE);
        $relationships = $xpath->query('/rel:Relationships/rel:Relationship');
        foreach ($relationships as $relationship) {
            if ($relationship instanceof \DOMElement
                && $relationship->getAttribute('Type') === 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument'
                && $relationship->getAttribute('TargetMode') !== 'External'
                && ltrim($relationship->getAttribute('Target'), '/') === 'xl/workbook.xml') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, true> $entryNames */
    private function hasJournalWorksheet(\DOMDocument $workbook, \DOMDocument $relationships, array $entryNames): bool
    {
        $workbookXpath = new \DOMXPath($workbook);
        $workbookXpath->registerNamespace('sheet', self::SPREADSHEET_NAMESPACE);
        $workbookXpath->registerNamespace('r', self::DOCUMENT_RELATIONSHIP_NAMESPACE);
        $journal = $workbookXpath->query('//sheet:sheets/sheet:sheet[@name="NHAT KY DOAN"]')->item(0);
        if (! $journal instanceof \DOMElement) {
            return false;
        }

        $relationshipId = $journal->getAttributeNS(self::DOCUMENT_RELATIONSHIP_NAMESPACE, 'id');
        if ($relationshipId === '') {
            return false;
        }

        $relationshipsXpath = new \DOMXPath($relationships);
        $relationshipsXpath->registerNamespace('rel', self::OFFICE_RELATIONSHIPS_NAMESPACE);
        $relationship = null;
        foreach ($relationshipsXpath->query('/rel:Relationships/rel:Relationship') as $candidate) {
            if ($candidate instanceof \DOMElement && $candidate->getAttribute('Id') === $relationshipId) {
                $relationship = $candidate;
                break;
            }
        }
        if (! $relationship instanceof \DOMElement
            || $relationship->getAttribute('Type') !== self::WORKSHEET_RELATIONSHIP_TYPE
            || $relationship->getAttribute('TargetMode') === 'External') {
            return false;
        }

        $target = str_replace('\\', '/', $relationship->getAttribute('Target'));
        $worksheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
        $segments = [];
        foreach (explode('/', $worksheetPath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return false;
                }
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return isset($entryNames[implode('/', $segments)]);
    }
}
