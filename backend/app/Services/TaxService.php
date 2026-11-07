<?php

namespace App\Services;

use App\Models\TaxComponent;
use App\Support\Money;

/* Perhitungan PPh dari komponen yang dikelola HRD (ekstensi modul PPh)
   Modul ini opsional dan belum diaktifkan pada fase pertama */
class TaxService
{
    public function isEnabled(): bool
    {
        // mengecualikan perhitungan PPh/full payroll
        return false;
    }

    /**
     * Hitung total PPh.
     * Seluruh nominal menggunakan string desimal agar tidak kehilangan
     * presisi akibat floating-point.
     *
     * @return array{
     *     total: string,
     *     enabled: bool,
     *     components: array
     * }
     */
    public function compute(string|int $baseAmount, string|int $deductionAmount): array
    {
        if (! $this->isEnabled()) {
            return [
                'total' => '0.00',
                'enabled' => false,
                'components' => [],
            ];
        }

        $baseAmount = Money::normalize($baseAmount);
        $deductionAmount = Money::normalize($deductionAmount);

        $afterAttendance = Money::rawFinalAmount(
            $baseAmount,
            $deductionAmount,
            '0.00'
        );

        $components = TaxComponent::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $total = '0.00';
        $breakdown = [];

        foreach ($components as $component) {
            $basis = $component->base_on === TaxComponent::BASE_AFTER_ATTENDANCE
                ? $afterAttendance
                : $baseAmount;

            $value = Money::normalize((string) $component->value);

            $amount = $component->calc_type === TaxComponent::CALC_FIXED
                ? $value
                : $this->percentageAmount($basis, $value);

            // DEDUCTION menambah potongan; ALLOWANCE mengurangi total potongan
            $signed = $component->type === TaxComponent::TYPE_ALLOWANCE
                ? bcsub('0.00', $amount, 2)
                : $amount;

            $total = bcadd($total, $signed, 2);

            $breakdown[] = [
                'code' => $component->code,
                'name' => $component->name,
                'type' => $component->type,
                'calc_type' => $component->calc_type,
                'value' => $value,
                'base_on' => $component->base_on,
                'basis' => $basis,
                'amount' => $amount,
                'signed' => $signed,
            ];
        }

        return [
            'total' => bccomp($total, '0.00', 2) > 0
                ? $total
                : '0.00',
            'enabled' => true,
            'components' => $breakdown,
        ];
    }

    /* bHitung persentase dengan pembulatan 2 angka desimal.
      Tidak menggunakan float dan tidak membulatkan ke rupiah */
    private function percentageAmount(string $basis, string $percentage): string
    {
        $product = bcmul($basis, $percentage, 4);
        $rawAmount = bcdiv($product, '100', 4);

        // Pembulatan half-up sampai dua angka desimal.
        $adjusted = bccomp($rawAmount, '0.0000', 4) < 0
            ? bcsub($rawAmount, '0.0050', 4)
            : bcadd($rawAmount, '0.0050', 4);

        return Money::normalize(bcdiv($adjusted, '1', 2));
    }

    /* Nominal akhir sebelum pembulatan Rp1.000.
      Nilai negatif tetap dipertahankan */
    public function finalAmount(
        string|int $baseAmount,
        string|int $deductionAmount,
        string|int $pphAmount
    ): string {
        return Money::rawFinalAmount(
            $baseAmount,
            $deductionAmount,
            $pphAmount
        );
    }

    /* Simulasi hitung tanpa menyimpan apa pun */
    public function preview(string|int $baseAmount, string|int $deductionAmount): array
    {
        $baseAmount = Money::normalize($baseAmount);
        $deductionAmount = Money::normalize($deductionAmount);

        $pph = $this->compute($baseAmount, $deductionAmount);

        return [
            'enabled' => $pph['enabled'],
            'base_amount' => $baseAmount,
            'deduction_amount' => $deductionAmount,
            'pph_amount' => $pph['total'],
            'components' => $pph['components'],
            'final_amount' => $this->finalAmount(
                $baseAmount,
                $deductionAmount,
                $pph['total']
            ),
        ];
    }
}