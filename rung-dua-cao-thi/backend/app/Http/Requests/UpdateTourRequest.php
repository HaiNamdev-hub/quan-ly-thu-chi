<?php

namespace App\Http\Requests;

class UpdateTourRequest extends StoreTourRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tour')) ?? false;
    }
}
