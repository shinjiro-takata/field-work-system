<?php

namespace App\Support;

/**
 * 点検項目をグループ単位で定義する共通処理。各グループのnameは画面表示名、itemsはその中の項目一覧を表す。
 * 各項目のidは保存時にも使う固定キー、location/item/methodは画面表示用、type/unitは入力方式と単位を表す。
 */
abstract class Checklist
{
    /**
     * 点検画面に表示する順序で、グループと項目を返す。nameはグループ名、itemsは点検項目の配列。
     * 各項目はid（保存用の固定キー）、location（箇所）、item（確認内容）、method（方法）、
     * type（数値入力などの種類）、unit（数値の単位）を持つ。
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
    abstract public static function groups(): array;

    /**
     * 固定IDをキーにした一覧へ変換し、各項目に所属グループ名を追加する。
     *
     * @return array<string, array<string, mixed>>
     */
    public static function items(): array
    {
        $items = [];

        foreach (static::groups() as $group) {
            foreach ($group['items'] as $item) {
                $item['group'] = $group['name'];
                $items[$item['id']] = $item;
            }
        }

        return $items;
    }
}
