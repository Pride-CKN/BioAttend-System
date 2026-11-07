<?php

namespace App\Support;

use InvalidArgumentException;

/* Aritmetika uang yang aman
 
  Seluruh perhitungan memakai INTEGER sen, bukan floating point
  Satu rupiah = 100 sen; kelipatan Rp1.000 = 100.000 sen
 
  Aturan:
    deduction_amount = bulatkan ke rupiah terdekat(base * percentage / 100)
    raw_final_amount = base - deduction_amount - pph_amount
    final_amount     = raw_final_amount > 0
                         ? ceil(raw_final_amount / 1000) * 1000
                         : raw_final_amount
    rounding_adjustment = final_amount - raw_final_amount
 
  Pembulatan HANYA sekali di akhir dan HANYA pada nominal akhir */
final class Money
{
    public const SEN_PER_RUPIAH = 100;
    public const SEN_PER_THOUSAND = 100_000;

    /* Ubah string/float nominal menjadi integer sen tanpa melewati float */
    public static function toCents(string|int|float $amount): int
    {
        $value = is_float($amount) ? number_format($amount, 2, '.', '') : (string) $amount;
        $value = trim($value);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("Nominal tidak valid: {$value}");
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$rupiah, $fraction] = array_pad(explode('.', $value), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        $cents = ((int) $rupiah) * self::SEN_PER_RUPIAH + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    /* Ubah integer sen kembali menjadi string desimal 2 angka */
    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);
        $rupiah = intdiv($cents, self::SEN_PER_RUPIAH);
        $fraction = $cents % self::SEN_PER_RUPIAH;

        return ($negative ? '-' : '').$rupiah.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    /* Nominal potongan = N x P / 100, dibulatkan ke RUPIAH terdekat
      (bukan ke kelipatan Rp1.000) sebelum dikurangkan */
    public static function deductionAmount(string|int|float $base, string|int|float $percentage): string
    {
        // D = N x P / 100
        // N dalam sen; P dalam persen dengan 2 desimal (2.00% -> 200)
        // D_rupiah = (N_sen * P_ratusan) / 1_000_000
        // BCMath dipakai agar perkalian besar tidak overflow / tidak float
        $baseCents = (string) self::toCents($base);
        $pct = is_float($percentage) ? number_format($percentage, 2, '.', '') : (string) $percentage;
        $pctHundredths = (string) self::toCents($pct);

        $numerator = bcmul($baseCents, $pctHundredths, 0);
        // Bulatkan ke rupiah terdekat (half-up): + 500.000 sebelum bagi 1.000.000
        $roundedRupiah = bcdiv(bcadd($numerator, '500000'), '1000000', 0);

        return self::fromCents(((int) $roundedRupiah) * self::SEN_PER_RUPIAH);
    }

    /* Nominal akhir sebelum pembulatan (masih bisa bernilai negatif) */
    public static function rawFinalAmount(
        string|int|float $base,
        string|int|float $deduction,
        string|int|float $pph = 0
    ): string {
        return self::fromCents(
            self::toCents($base) - self::toCents($deduction) - self::toCents($pph)
        );
    }

    /**
     * Bulatkan KE ATAS ke kelipatan Rp1.000 hanya bila > 0
     * Nilai nol/negatif dibiarkan apa adanya; kelipatan Rp1.000 tidak berubah
     *
     * @return array{raw_final_amount: string, final_amount: string, rounding_adjustment: string}
     */
    public static function applyFinalRounding(string|int|float $rawFinalAmount): array
    {
        $cents = self::toCents($rawFinalAmount);
        $raw = self::fromCents($cents);

        if ($cents <= 0) {
            return [
                'raw_final_amount' => $raw,
                'final_amount' => $raw,
                'rounding_adjustment' => '0.00',
            ];
        }

        $remainder = $cents % self::SEN_PER_THOUSAND;
        $finalCents = $remainder === 0
            ? $cents
            : $cents + (self::SEN_PER_THOUSAND - $remainder);

        return [
            'raw_final_amount' => $raw,
            'final_amount' => self::fromCents($finalCents),
            'rounding_adjustment' => self::fromCents($finalCents - $cents),
        ];
    }

    /* Hitung seluruh komponen nominal sekaligus */
    public static function calculate(
        string|int|float $base,
        string|int|float $percentage,
        string|int|float $pph = 0
    ): array {
        $deduction = self::deductionAmount($base, $percentage);
        $raw = self::rawFinalAmount($base, $deduction, $pph);

        return array_merge([
            'base_amount' => self::normalize($base),
            'total_percentage' => self::normalize($percentage),
            'deduction_amount' => $deduction,
            'pph_amount' => self::normalize($pph),
        ], self::applyFinalRounding($raw));
    }

    public static function normalize(string|int|float $amount): string
    {
        return self::fromCents(self::toCents($amount));
    }
}
