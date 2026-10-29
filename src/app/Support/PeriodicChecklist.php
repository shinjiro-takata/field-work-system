<?php

namespace App\Support;

/**
 * 定期点検（原料場の油の補充・交換、6月・12月）のチェックリストを定義する。
 * グループのnameは機器名、itemsは入力項目一覧。各項目のidは保存キー、location/item/methodは表示、
 * typeは入力形式、actionsは選べる作業、unitは入力する量の単位を表す。油種と規定量はmethodに表示する。
 */
class PeriodicChecklist extends Checklist
{
    /**
     * 機器ごとにグループを作り、点検画面に表示する順序で返す。各項目の構造はクラス説明を参照。
     */
    public static function groups(): array
    {
        $groups = [];

        // 各行は識別子、設備箇所、油種、規定量、選べる作業の順。作業は用紙の斜線に合わせて機器ごとに限定する。
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
     * 機器ごとの定義を画面表示・保存用の項目配列に組み立てる。
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
     * 保存値refill/replaceに対応する画面表示名を返す。
     *
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return ['refill' => '補充', 'replace' => '交換'];
    }
}
