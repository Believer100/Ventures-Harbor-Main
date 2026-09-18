<?php

/**
 * One decimal place, but only when there is one to show: 42.1 lakh stays 42.1,
 * a round 42 lakh stays 42 rather than becoming 42.0.
 *
 * The lakh figure used to be round()ed to a whole number, so ₹42,10,000 read as
 * "42L+" and the trailing ₹10,000s simply vanished — the same money the venture
 * cards were already reporting as ₹42.1L via compactINR(). Same rule as
 * compactINR() / VH.card.money(); keep the three in step.
 */
function vh_stat_figure($n): float
{
    return round((float)$n, 1);
}

function vh_site_stats(mysqli $mysqli): array
{
    $scalar = function ($sql) use ($mysqli) {
        $r = $mysqli->query($sql);
        if (!$r) return 0;
        $row = $r->fetch_row();
        return $row ? (float)$row[0] : 0;
    };

    $activeCount = (int)$scalar("SELECT COUNT(*) FROM ventures WHERE status = 'active'");
    $pooledRaw   = $scalar("SELECT COALESCE(SUM(raised_capital),0) FROM ventures");
    $memberCount = (int)$scalar("SELECT COUNT(*) FROM venture_members");
    $pooledCr    = $pooledRaw / 10000000; // 1 crore = 10,000,000

    return [
        ['key' => 'active_ventures', 'value' => $activeCount, 'suffix' => '+', 'label' => 'Listed Assets'],

        $pooledCr >= 1
            ? ['key' => 'capital_pooled', 'value' => vh_stat_figure($pooledCr), 'suffix' => 'Cr+', 'label' => 'Capital Pooled']
            : ['key' => 'capital_pooled', 'value' => vh_stat_figure($pooledRaw / 100000), 'suffix' => 'L+', 'label' => 'Capital Pooled'],
        ['key' => 'members_joined', 'value' => $memberCount, 'suffix' => '+', 'label' => 'Members Joined'],
    ];
}

function vh_stat_value(array $stats, string $key, $default = 0)
{
    foreach ($stats as $row) {
        if (($row['key'] ?? null) === $key) return $row['value'];
    }
    return $default;
}
