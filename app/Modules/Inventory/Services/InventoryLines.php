<?php

namespace App\Modules\Inventory\Services;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Validates "lines[i][material|quantity|unit_cost]" input and resolves materials by public id.
 */
class InventoryLines
{
    /**
     * @return array<int, array{material: Material, quantity: string, unit_cost: ?string}>
     */
    public static function from(Request $request, bool $cost = false, bool $signed = false, bool $costRequired = true): array
    {
        $lines = collect($request->input('lines', []))->map(fn ($l) => [
            'material' => $l['material'] ?? null,
            'quantity' => isset($l['quantity']) ? str_replace(',', '', (string) $l['quantity']) : null,
            'unit_cost' => isset($l['unit_cost']) && $l['unit_cost'] !== '' ? str_replace(',', '', (string) $l['unit_cost']) : null,
        ])->values()->all();
        $request->merge(['lines' => $lines]);

        $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.material' => ['required', 'string', 'size:16', 'distinct'],
            'lines.*.quantity' => $signed
                ? ['required', 'numeric', 'not_in:0', 'between:-99999999,99999999', 'decimal:0,3']
                : ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,3'],
            'lines.*.unit_cost' => $cost ? [$costRequired ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,4'] : ['prohibited'],
        ], [
            'lines.required' => 'Add at least one material.',
            'lines.*.material.required' => 'Select a material.',
            'lines.*.material.distinct' => 'Each material can appear once.',
            'lines.*.quantity.gt' => 'Quantity must be greater than zero.',
            'lines.*.quantity.not_in' => 'Quantity cannot be zero.',
            'lines.*.quantity.decimal' => 'Use at most 3 decimals.',
        ]);

        $materials = Material::query()->active()->whereIn('public_id', array_column($lines, 'material'))->get()->keyBy('public_id');

        return array_map(function (array $line, int $i) use ($materials) {
            $material = $materials[$line['material']] ?? throw ValidationException::withMessages(["lines.{$i}.material" => 'Select an active material.']);

            return ['material' => $material, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'], 'qty_delta' => $line['quantity']];
        }, $lines, array_keys($lines));
    }
}
