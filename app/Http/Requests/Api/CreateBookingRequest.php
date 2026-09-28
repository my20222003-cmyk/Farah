<?php

namespace App\Http\Requests\Api;

class CreateBookingRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
            'booking_slot' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
