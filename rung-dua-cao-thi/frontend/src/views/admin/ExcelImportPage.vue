<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AlertTriangle, CheckCircle2, FileSpreadsheet, LoaderCircle, Search, Upload } from 'lucide-vue-next'
import { api } from '../../services/api'

interface ImportBatch {
  id: number
  source_name: string
  sha256: string
  status: string
  source_sheet: string
  summary: {
    total_rows: number; ready_rows: number; rows_needing_review: number; issue_count: number; archived_legacy_expense_rows: number
    source_totals: Record<string, number>; ready_totals: Record<string, number>
  } | null
  created_at: string
  analyzed_at: string | null
  confirmed_at: string | null
  dashboard_pending_tours?: number
}

interface ImportRow {
  id: number
  source_row: number
  status: string
  mapped_values: Record<string, unknown> | null
  issues: string[] | null
  source_values: Record<string, { value: unknown; formula_missing_cache: boolean }>
}

const batches = ref<ImportBatch[]>([])
const selectedBatch = ref<ImportBatch | null>(null)
const rows = ref<ImportRow[]>([])
const file = ref<File | null>(null)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const searchText = ref('')
const filteredRows = computed(() => rows.value.filter((row) => {
  const data = row.mapped_values ?? {}
  const haystack = [data.code, data.tour_date, data.guide_name, data.guide_phone, ...(row.issues ?? [])].join(' ').toLocaleLowerCase()
  return haystack.includes(searchText.value.toLocaleLowerCase())
}))

async function loadBatches(): Promise<void> {
  const response = await api.get<{ data: ImportBatch[] }>('/api/admin/imports')
  batches.value = response.data.data
}

async function loadPreview(batch: ImportBatch): Promise<void> {
  busy.value = true
  error.value = ''
  try {
    const response = await api.get<{ batch: ImportBatch; rows: { data: ImportRow[] } }>(`/api/admin/imports/${batch.id}/preview`, { params: { per_page: 600 } })
    selectedBatch.value = response.data.batch
    rows.value = response.data.rows.data
  } catch {
    error.value = 'Không tải được bản xem trước.'
  } finally {
    busy.value = false
  }
}

async function uploadAndAnalyze(): Promise<void> {
  if (!file.value) return
  busy.value = true
  error.value = ''
  notice.value = ''
  try {
    const body = new FormData()
    body.append('file', file.value)
    const uploaded = await api.post<{ batch: ImportBatch }>('/api/admin/imports/upload', body)
    if (uploaded.data.batch.status !== 'uploaded') {
      await loadBatches()
      await loadPreview(uploaded.data.batch)
      notice.value = 'Workbook có cùng SHA-256 đã tồn tại. Đã mở lô cũ, không tạo lô trùng.'
      return
    }
    const result = await api.post<{ batch: ImportBatch }>(`/api/admin/imports/${uploaded.data.batch.id}/analyze`)
    await loadBatches()
    await loadPreview(result.data.batch)
    notice.value = 'Đã phân tích file. Các bản ghi chưa được đưa vào sổ nghiệp vụ.'
  } catch (exception: unknown) {
    const response = (exception as { response?: { data?: { errors?: Record<string, string[]> } } }).response
    error.value = Object.values(response?.data?.errors ?? {}).flat()[0] ?? 'Không thể tải hoặc phân tích file XLSM.'
  } finally {
    busy.value = false
  }
}

async function analyzeSelected(): Promise<void> {
  if (!selectedBatch.value) return
  busy.value = true
  error.value = ''
  try {
    const response = await api.post<{ batch: ImportBatch }>(`/api/admin/imports/${selectedBatch.value.id}/analyze`)
    await loadBatches()
    await loadPreview(response.data.batch)
    notice.value = 'Đã phân tích lại, chưa ghi dữ liệu nghiệp vụ.'
  } catch {
    error.value = 'Không phân tích lại được workbook.'
  } finally {
    busy.value = false
  }
}

async function confirmImport(): Promise<void> {
  if (!selectedBatch.value) return
  const count = selectedBatch.value.summary?.ready_rows ?? 0
  if (count === 0 || !window.confirm(`Xác nhận nhập ${count} đoàn đã sẵn sàng? Chỉ các dòng bạn xác nhận sẽ được đưa vào Dashboard; các dòng cần đối soát vẫn được giữ lại.`)) return
  busy.value = true
  error.value = ''
  try {
    const response = await api.post<{ batch: ImportBatch; result: { imported: number; skipped: number } }>(`/api/admin/imports/${selectedBatch.value.id}/confirm`)
    await loadBatches()
    await loadPreview(response.data.batch)
    notice.value = `Đã nhập và cập nhật Dashboard ${response.data.result.imported} đoàn; bỏ qua ${response.data.result.skipped} bản ghi xung đột. Các dòng cần đối soát vẫn được giữ nguyên.`
  } catch {
    error.value = 'Không thể xác nhận nhập. Hãy kiểm tra lại lô và quyền tài khoản.'
  } finally {
    busy.value = false
  }
}

async function includeInDashboard(): Promise<void> {
  if (!selectedBatch.value) return
  if (!window.confirm('Bạn đã đối soát số liệu lô này với Excel và muốn đưa các đoàn đã nhập vào Dashboard?')) return
  busy.value = true
  error.value = ''
  try {
    const response = await api.post<{ result: { included_tours: number } }>(`/api/admin/imports/${selectedBatch.value.id}/include-in-dashboard`)
    await loadBatches()
    notice.value = `Đã đưa ${response.data.result.included_tours} đoàn đã đối soát vào Dashboard.`
  } catch {
    error.value = 'Không thể phê duyệt lô cho Dashboard.'
  } finally {
    busy.value = false
  }
}

function formatMoney(value: unknown): string {
  return typeof value === 'number' ? new Intl.NumberFormat('vi-VN').format(value) : '—'
}

onMounted(async () => {
  try { await loadBatches() } catch { error.value = 'Không tải được lịch sử import.' }
})
</script>

<template>
  <section class="mx-auto max-w-7xl space-y-5 sm:space-y-6">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-800">Quản trị dữ liệu</p><h1 class="mt-1 text-2xl font-bold text-slate-900 sm:text-3xl">Import Excel lịch sử</h1><p class="mt-2 max-w-3xl text-sm text-slate-600">Tải workbook XLSM để phân tích sheet nhật ký, xem lỗi và trùng mã trước khi nhập. Macro không được chạy.</p></div>
    </header>

    <div v-if="error" class="flex gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"><AlertTriangle :size="18" class="shrink-0" />{{ error }}</div>
    <div v-if="notice" class="flex gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900"><CheckCircle2 :size="18" class="shrink-0" />{{ notice }}</div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="flex items-center gap-2 font-semibold text-slate-900"><Upload :size="18" />Tải workbook</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">Chỉ nhận XLSM, tối đa 20 MB. File lưu riêng tư và được nhận diện bằng SHA-256 để chống import trùng.</p>
        <label class="mt-4 block cursor-pointer rounded-xl border-2 border-dashed border-slate-300 p-5 text-center transition hover:border-emerald-600 hover:bg-emerald-50/40">
          <FileSpreadsheet :size="25" class="mx-auto text-emerald-800" /><span class="mt-2 block break-all text-sm font-medium text-slate-700">{{ file?.name ?? 'Chọn file .xlsm' }}</span>
          <input class="sr-only" type="file" accept=".xlsm" @change="file = ($event.target as HTMLInputElement).files?.[0] ?? null">
        </label>
        <button class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 text-sm font-semibold text-white hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!file || busy" @click="uploadAndAnalyze"><LoaderCircle v-if="busy" :size="17" class="animate-spin" /><Upload v-else :size="17" />Tải lên và phân tích</button>
      </section>

      <section class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5">
        <h2 class="font-semibold text-amber-950">Nguyên tắc nhập an toàn</h2>
        <ul class="mt-3 list-inside list-disc space-y-1.5 text-sm leading-5 text-amber-950/80">
          <li>Chỉ đọc <strong>NHAT KY DOAN</strong>; không nhập sheet tổng hợp hoặc nhập theo ngày.</li><li>Giữ snapshot lịch sử; không tính lại theo bảng giá hiện tại.</li>
          <li>Đoàn import chờ đối soát và không tính vào Dashboard đến khi được xác nhận nghiệp vụ riêng.</li><li>Ảnh lịch sử không tạo bút toán tồn kho; tồn đầu kỳ chưa được xác nhận.</li>
          <li>Chi phí trong sheet lưu chi cũ chỉ được lưu trữ, không cộng vào báo cáo.</li>
        </ul>
      </section>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div><h2 class="font-semibold text-slate-900">Lịch sử các lô</h2><p class="mt-1 text-xs text-slate-500">Chọn một lô để xem preview và trạng thái.</p></div>
        <select class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm" :value="selectedBatch?.id ?? ''" @change="(event) => { const batch = batches.find((item) => item.id === Number((event.target as HTMLSelectElement).value)); if (batch) loadPreview(batch) }">
          <option value="">Chọn lô import</option><option v-for="batch in batches" :key="batch.id" :value="batch.id">#{{ batch.id }} · {{ batch.source_name }} · {{ batch.status }}</option>
        </select>
      </div>
      <div v-if="selectedBatch" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Tổng đoàn</p><p class="mt-1 text-lg font-bold">{{ selectedBatch.summary?.total_rows ?? 0 }}</p></div>
        <div class="rounded-xl bg-emerald-50 p-3"><p class="text-xs text-emerald-800">Sẵn sàng</p><p class="mt-1 text-lg font-bold text-emerald-950">{{ selectedBatch.summary?.ready_rows ?? 0 }}</p></div>
        <div class="rounded-xl bg-amber-50 p-3"><p class="text-xs text-amber-800">Cần đối soát</p><p class="mt-1 text-lg font-bold text-amber-950">{{ selectedBatch.summary?.rows_needing_review ?? 0 }}</p></div>
        <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">Chi cũ lưu trữ</p><p class="mt-1 text-lg font-bold">{{ selectedBatch.summary?.archived_legacy_expense_rows ?? 0 }} dòng</p></div>
      </div>
      <div v-if="selectedBatch?.summary" class="mt-3 grid gap-3 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-3"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Snapshot toàn file Excel</p><div class="mt-2 grid grid-cols-3 gap-2 text-sm"><div><p class="text-xs text-slate-500">Tổng thu</p><strong>{{ formatMoney(selectedBatch.summary.source_totals.total_revenue) }}đ</strong></div><div><p class="text-xs text-slate-500">Tổng chi</p><strong>{{ formatMoney(selectedBatch.summary.source_totals.total_expenses) }}đ</strong></div><div><p class="text-xs text-slate-500">Lãi gộp</p><strong>{{ formatMoney(selectedBatch.summary.source_totals.gross_profit) }}đ</strong></div></div></div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Snapshot các dòng sẵn sàng</p><div class="mt-2 grid grid-cols-3 gap-2 text-sm"><div><p class="text-xs text-slate-500">Tổng thu</p><strong>{{ formatMoney(selectedBatch.summary.ready_totals.total_revenue) }}đ</strong></div><div><p class="text-xs text-slate-500">Tổng chi</p><strong>{{ formatMoney(selectedBatch.summary.ready_totals.total_expenses) }}đ</strong></div><div><p class="text-xs text-slate-500">Lãi gộp</p><strong>{{ formatMoney(selectedBatch.summary.ready_totals.gross_profit) }}đ</strong></div></div></div>
      </div>
      <div v-if="selectedBatch" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <label class="relative block w-full sm:max-w-xs"><Search :size="16" class="absolute left-3 top-3 text-slate-400" /><input v-model="searchText" class="min-h-11 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm" placeholder="Tìm mã đoàn, ngày, HDV..."></label>
        <div class="flex flex-col gap-2 sm:flex-row">
          <button v-if="selectedBatch.status !== 'confirmed'" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-medium hover:bg-slate-50 disabled:opacity-50" :disabled="busy" @click="analyzeSelected">Phân tích lại</button>
          <button v-if="selectedBatch.status === 'analyzed'" class="min-h-11 rounded-xl bg-emerald-800 px-4 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50" :disabled="busy || !selectedBatch.summary?.ready_rows" @click="confirmImport">Xác nhận import {{ selectedBatch.summary?.ready_rows ?? 0 }} đoàn</button>
          <button v-if="selectedBatch.status === 'confirmed' && (selectedBatch.dashboard_pending_tours ?? 0) > 0" class="min-h-11 rounded-xl bg-amber-700 px-4 text-sm font-semibold text-white hover:bg-amber-800 disabled:opacity-50" :disabled="busy" @click="includeInDashboard">Đã đối soát · bật Dashboard ({{ selectedBatch.dashboard_pending_tours }})</button>
        </div>
      </div>
      <div v-if="selectedBatch" class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
        <table class="min-w-[900px] w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-3">Dòng</th><th class="px-3 py-3">Ngày / mã đoàn</th><th class="px-3 py-3">HDV</th><th class="px-3 py-3">Khách</th><th class="px-3 py-3">Thu vé · tổng thu</th><th class="px-3 py-3">Thanh toán</th><th class="px-3 py-3">Trạng thái / vấn đề</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in filteredRows" :key="row.id" class="align-top">
              <td class="px-3 py-3 text-slate-500">{{ row.source_row }}</td><td class="px-3 py-3"><strong>{{ row.mapped_values?.code ?? '—' }}</strong><p class="mt-1 text-xs text-slate-500">{{ row.mapped_values?.tour_date ?? '—' }}</p></td>
              <td class="px-3 py-3">{{ row.mapped_values?.guide_name ?? '—' }}<p class="mt-1 text-xs text-slate-500">{{ row.mapped_values?.guide_phone ?? '—' }}</p></td><td class="px-3 py-3">{{ Number(row.mapped_values?.total_guests ?? 0) }} khách</td>
              <td class="px-3 py-3">{{ formatMoney(row.source_values.AC?.value) }}<p class="mt-1 text-xs text-slate-500">{{ formatMoney(row.source_values.AG?.value) }}</p></td><td class="px-3 py-3">{{ row.mapped_values?.ticket_payment_method ?? 'Chưa phân loại' }}</td>
              <td class="max-w-sm px-3 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs">{{ row.status }}</span><ul v-if="row.issues?.length" class="mt-2 space-y-1 text-xs text-amber-800"><li v-for="issue in row.issues" :key="issue">{{ issue }}</li></ul></td>
            </tr><tr v-if="filteredRows.length === 0"><td colspan="7" class="px-3 py-8 text-center text-slate-500">Không có dòng phù hợp.</td></tr>
          </tbody>
        </table>
      </div>
      <p v-else class="mt-5 rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">Chưa chọn lô nào.</p>
    </section>
  </section>
</template>
