<?php

namespace App\Support;

/**
 * 定期点検（原料場の油の補充・交換、6月・12月）のチェックリスト定義。
 * 各機器に補充・交換の種類と量（L）を入力し、油種と規定油量は方法欄に固定表示する。
 */
class PeriodicChecklist extends Checklist
{
    /**
     * 点検画面に表示する順序で、グループおよび項目一覧を返す。
     */
    public static function groups(): array
    {
        $groups = [];

        // 補充・交換の選択肢は用紙の斜線に合わせて機器ごとに限定する。
        foreach ([1, 2, 3] as $no) {
            $groups[] = self::group("ミキサーNo.{$no}", "mixer{$no}", [
                ['cone', 'リングコーン変速機', '(ツボサカエナジー)TD-10', '8.5L', ['replace', 'refill']],
                ['reducer', '減速部', 'メカニックオイル220', '約9L', ['replace', 'refill']],
                ['bearing', '軸受部', 'スーパーマルチオイル#2', '6L', ['refill']],
                ['lift', '昇降油圧ユニット', 'スーパーハイドロリックフルイド32', null, ['refill']],
                ['pump', '真空ポンプ', 'スーパーエースパック68', '6L', ['replace']],
            ]);
        }

        $groups[] = self::group('プレス機', 'press', [
            ['lift', 'テーブルリフト', 'スーパーハイドロリックフルイド32', '12L', ['refill']],
        ]);

        return $groups;
    }

    /**
     * 機器ごとに、補充・交換の種類と量を入力する項目を作る。
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string|null, 4: list<string>}> $equipments
     * @return array{name: string, items: list<array<string, mixed>>}
     */
    private static function group(string $name, string $key, array $equipments): array
    {
        $items = [];

        foreach ($equipments as [$id, $equipment, $oil, $capacity, $actions]) {
            $items[] = [
                'id' => "p-{$key}-{$id}",
                'location' => $equipment,
                'item' => '補充・交換',
                'method' => $oil . ($capacity ? "／{$capacity}" : ''),
                'type' => 'oil',
                'actions' => $actions,
                'unit' => 'L',
            ];
        }

        return ['name' => $name, 'items' => $items];
    }

    /**
     * 作業の種類ごとの表示名を返す。
     *
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return ['refill' => '補充', 'replace' => '交換'];
    }
}
