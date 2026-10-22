<?php

namespace Split\Domain;

use Split\Api\ApiException;

/**
 * The arithmetic, all in whole öre so nothing is lost to rounding:
 * dividing an expense, balances, and the fewest payments that settle a group.
 */
final class Ledger
{
    /**
     * Shares for an expense. $inputs holds öre (amount mode) or percent (percent mode) per person.
     *
     * @param list<int> $people
     * @return array<int, int> person id => öre, summing exactly to $amount
     */
    public static function shares(int $amount, string $mode, array $people, array $inputs = []): array
    {
        $people = array_values(array_unique(array_map('intval', $people)));
        sort($people);
        if (!$people) {
            throw new ApiException(ps_t('Välj minst en person som delar på kostnaden.'), 422);
        }
        if ($mode === 'equal') {
            return self::spread($amount, array_fill_keys($people, 1));
        }
        if ($mode === 'amount') {
            $raw = [];
            foreach ($people as $id) {
                $value = (int) ($inputs[$id] ?? 0);
                if ($value <= 0) {
                    throw new ApiException(ps_t('Varje person behöver ett belopp större än 0.'), 422);
                }
                $raw[$id] = $value;
            }
            if (array_sum($raw) !== $amount) {
                throw new ApiException(ps_t('Beloppen måste bli ') . money($amount) . ' tillsammans (nu ' . money(array_sum($raw)) . ').', 422);
            }
            return $raw;
        }
        if ($mode === 'percent') {
            $raw = [];
            foreach ($people as $id) {
                $value = (float) ($inputs[$id] ?? 0);
                if ($value <= 0) {
                    throw new ApiException('Varje person behöver en andel större än 0 %.', 422);
                }
                $raw[$id] = $value;
            }
            if (abs(array_sum($raw) - 100) > 0.01) {
                throw new ApiException('Andelarna måste bli 100 % tillsammans.', 422);
            }
            return self::spread($amount, $raw);
        }
        throw new ApiException(ps_t('Okänt sätt att dela.'), 422);
    }

    /** Splits $amount by weight; leftover öre go to the largest remainders, so the sum is exact. */
    private static function spread(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $shares = [];
        $remainders = [];
        foreach ($weights as $id => $weight) {
            $exact = $amount * $weight / $total;
            $shares[$id] = (int) floor($exact);
            $remainders[$id] = $exact - $shares[$id];
        }
        $left = $amount - array_sum($shares);
        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, $left) as $id) {
            $shares[$id]++;
        }
        ksort($shares);
        return $shares;
    }

    /**
     * Balance per person: what they paid minus their shares, adjusted by
     * settlements. Positive means the group owes them.
     *
     * @return array<int, int>
     */
    public static function balances(array $memberIds, array $expenses, array $settlements): array
    {
        $balances = array_fill_keys(array_map('intval', $memberIds), 0);
        foreach ($expenses as $expense) {
            $balances[(int) $expense['paid_by']] = ($balances[(int) $expense['paid_by']] ?? 0) + (int) $expense['amount'];
            foreach ($expense['shares'] as $id => $share) {
                $balances[$id] = ($balances[$id] ?? 0) - $share;
            }
        }
        foreach ($settlements as $settlement) {
            $balances[(int) $settlement['from_user']] = ($balances[(int) $settlement['from_user']] ?? 0) + (int) $settlement['amount'];
            $balances[(int) $settlement['to_user']] = ($balances[(int) $settlement['to_user']] ?? 0) - (int) $settlement['amount'];
        }
        return $balances;
    }

    /**
     * The fewest payments that make every balance zero: the largest debtor
     * pays the largest creditor, and so on.
     *
     * @return list<array{from: int, to: int, amount: int}>
     */
    public static function transfers(array $balances): array
    {
        $creditors = array_filter($balances, fn ($b) => $b > 0);
        $debtors = array_map(fn ($b) => -$b, array_filter($balances, fn ($b) => $b < 0));
        arsort($creditors);
        arsort($debtors);
        $transfers = [];
        while ($creditors && $debtors) {
            $to = array_key_first($creditors);
            $from = array_key_first($debtors);
            $amount = min($creditors[$to], $debtors[$from]);
            $transfers[] = ['from' => (int) $from, 'to' => (int) $to, 'amount' => $amount];
            $creditors[$to] -= $amount;
            $debtors[$from] -= $amount;
            if ($creditors[$to] === 0) {
                unset($creditors[$to]);
            }
            if ($debtors[$from] === 0) {
                unset($debtors[$from]);
            }
            arsort($creditors);
            arsort($debtors);
        }
        return $transfers;
    }

    /** "123,45" or "123.45" or "123" to öre. */
    public static function parseAmount(mixed $value): int
    {
        $text = str_replace([' ', "\u{a0}", 'kr'], '', trim((string) $value));
        $text = str_replace(',', '.', $text);
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $text)) {
            throw new ApiException(ps_t('Skriv ett belopp, till exempel 249,50.'), 422);
        }
        return (int) round(((float) $text) * 100);
    }
}
