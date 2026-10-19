<?php

namespace App\Support;

/**
 * 週次・月次など、点検項目をグループ単位で定義するチェックリストの共通処理。
 */
abstract class Checklist
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
    abstract public static function groups(): array;

    /**
     * 固定IDをキーにして、グループ名を含む項目一覧を返す。
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
