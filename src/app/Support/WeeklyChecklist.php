<?php

namespace App\Support;

/**
 * 週次点検（週末担当者又は保全担当者停止時点検）のチェックリスト定義。
 */
class WeeklyChecklist extends Checklist
{
    /**
     * 点検画面に表示する順序で、グループおよび項目一覧を返す。
     *
     * @return list<array{
     *     name: string,
     *     items: list<array{
     *         id: string,
     *         location: string,
     *         item: string,
     *         method: string,
     *         type?: 'numeric',
     *         unit?: string
     *     }>
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'name' => 'プラッター',
                'items' => [
                    ['id' => 'w-platter-battery', 'location' => 'バッテリー', 'item' => '電液の確認', 'method' => '目視'],
                ],
            ],
            [
                'name' => 'ミキサー（1）',
                'items' => [
                    ['id' => 'w-mixer1-pump-oil', 'location' => '真空ポンプ', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                    ['id' => 'w-mixer1-trap-oil', 'location' => 'オイルトラップ', 'item' => '油量（下段ゲージ中央）', 'method' => '目視'],
                ],
            ],
            [
                'name' => 'ミキサー（2）',
                'items' => [
                    ['id' => 'w-mixer2-pump-oil', 'location' => '真空ポンプ', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                    ['id' => 'w-mixer2-trap-oil', 'location' => 'オイルトラップ', 'item' => '油量（下段ゲージ中央）', 'method' => '目視'],
                ],
            ],
            [
                'name' => 'ミキサー（3）',
                'items' => [
                    ['id' => 'w-mixer3-pump-oil', 'location' => '真空ポンプ', 'item' => '油量（ゲージ中央）', 'method' => '目視'],
                    ['id' => 'w-mixer3-trap-oil', 'location' => 'オイルトラップ', 'item' => '油量（下段ゲージ中央）', 'method' => '目視'],
                ],
            ],
            [
                'name' => '電子天秤（可塑剤計量）',
                'items' => [
                    ['id' => 'w-scale-plasticizer-body', 'location' => '本体', 'item' => 'ガタはないか', 'method' => '手確認'],
                    ['id' => 'w-scale-plasticizer-panel', 'location' => 'パネル表示', 'item' => '動作状態', 'method' => '手確認'],
                ],
            ],
            [
                'name' => '電子天秤（顔料・安定剤計量）',
                'items' => [
                    ['id' => 'w-scale-pigment-body', 'location' => '本体', 'item' => 'ガタはないか', 'method' => '手確認'],
                    ['id' => 'w-scale-pigment-panel', 'location' => 'パネル表示', 'item' => '動作状態', 'method' => '手確認'],
                ],
            ],
            [
                'name' => '電子天秤（レオロシール計量）',
                'items' => [
                    ['id' => 'w-scale-reoloseal-body', 'location' => '本体', 'item' => 'ガタはないか', 'method' => '手確認'],
                    ['id' => 'w-scale-reoloseal-panel', 'location' => 'パネル表示', 'item' => '動作状態', 'method' => '手確認'],
                ],
            ],
            [
                'name' => '可塑剤ポンプ室',
                'items' => [
                    ['id' => 'w-pump-plasticizer', 'location' => 'ポンプ', 'item' => '漏洩', 'method' => '読取'],
                ],
            ],
            [
                'name' => '溶剤ポンプ室',
                'items' => [
                    ['id' => 'w-pump-solvent-belt', 'location' => 'ベルト', 'item' => 'たるみ・損傷', 'method' => '目視・手'],
                ],
            ],
            [
                'name' => '集塵機（1）',
                'items' => [
                    ['id' => 'w-dust1-regulator', 'location' => 'レギュレータ', 'item' => 'ドレン', 'method' => '水抜き'],
                    ['id' => 'w-dust1-filter-fine', 'location' => 'ウルトラファインフィルタ', 'item' => '破損・ダスト堆積状態', 'method' => '目視'],
                    ['id' => 'w-dust1-filter-cool', 'location' => '冷却風吸込口フィルタ', 'item' => '目詰まり', 'method' => '目視'],
                    ['id' => 'w-dust1-hood-wind', 'location' => '吸込口フード根元', 'item' => '吸込み量（風速）', 'method' => '風速計', 'type' => 'numeric', 'unit' => 'm/s'],
                ],
            ],
            [
                'name' => '集塵機（2）',
                'items' => [
                    ['id' => 'w-dust2-regulator', 'location' => 'レギュレータ', 'item' => 'ドレン', 'method' => '水抜き'],
                    ['id' => 'w-dust2-filter-fine', 'location' => 'ウルトラファインフィルタ', 'item' => '破損・ダスト堆積状態', 'method' => '目視'],
                    ['id' => 'w-dust2-filter-cool', 'location' => '冷却風吸込口フィルタ', 'item' => '目詰まり', 'method' => '目視'],
                    ['id' => 'w-dust2-hood-wind', 'location' => '吸込口フード根元', 'item' => '吸込み量（風速）', 'method' => '風速計', 'type' => 'numeric', 'unit' => 'm/s'],
                ],
            ],
        ];
    }
}
