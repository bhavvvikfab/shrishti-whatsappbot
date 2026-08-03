<?php

namespace App\Http\Controllers\Api\Masters;

use App\Http\Controllers\Api\ApiBaseController;
use App\Models\City;
use App\Traits\MasterApiTrait;
use Illuminate\Http\Request;

class CityController extends ApiBaseController
{
    use MasterApiTrait;

    protected function model(): string
    {
        return City::class;
    }

    protected function rules(Request $request, $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ];
    }
}
