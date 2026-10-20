@extends('layouts.app')

@section('title', '履歴の確認 | レジン使用量確認表')
@section('brand', 'レジン使用量確認表')
@section('brand-link', route('resin-usage.index'))
@section('show-home', true)

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
<style>
    .resin-usage-difference {
        background-color: #f8d7da;
        color: #842029;
        font-weight: 600;
    }

    .resin-usage-unconfirmed {
        background-color: #fff3cd;
        color: #664d03;
        font-weight: 600;
    }

    .resin-usage-audit table {
        margin-top: 8px;
    }
</style>
@endsection

@section('content')
<h1>レジン使用量の履歴</h1>

<div class="action-buttons">
    <a href="{{ route('resin-usage.create') }}" class="btn">新規入力</a>
    <a href="{{ route('resin-usage.index') }}" class="btn btn-secondary">メニューに戻る</a>
</div>

@if(!$setting)
    <div class="info-box">
        <p>使用量を集計する2種類の材料がまだ設定されていません。</p>
        @if(Auth::user()->is_admin)
            <a href="{{ route('resin-usage.settings.edit') }}" class="btn">レジン材料を設定する</a>
        @else
            <p>管理者に設定を依頼してください。</p>
        @endif
    </div>
@elseif($records->isEmpty())
    <div class="info-box"><p>登録された履歴はありません。</p></div>
@else
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>日付</th>
                    <th>確認者</th>
                    <th>レジン</th>
                    <th>前回在庫</th>
                    <th>使用量</th>
                    <th>補充数</th>
                    <th>計算在庫</th>
                    <th>実在庫</th>
                    <th>差異</th>
                    <th>上司確認</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    @foreach($record->items as $item)
                        <tr>
                            <td>{{ $record->usage_date->format('Y-m-d') }}</td>
                            <td>{{ $record->operator_name }}</td>
                            <td>{{ $item->material->name }}</td>
                            <td>{{ number_format($item->opening_stock, 3) }} {{ $item->material->unit }}</td>
                            <td>{{ number_format($item->used_quantity, 3) }} {{ $item->material->unit }}</td>
                            <td>{{ number_format($item->replenished_quantity, 3) }} {{ $item->material->unit }}</td>
                            <td>{{ number_format($item->calculated_stock, 3) }} {{ $item->material->unit }}</td>
                            <td>{{ number_format($item->actual_stock, 3) }} {{ $item->material->unit }}</td>
                            <td class="{{ (float) $item->adjustment_quantity !== 0.0 ? 'resin-usage-difference' : '' }}">
                                {{ $item->adjustment_quantity > 0 ? '+' : '' }}{{ number_format($item->adjustment_quantity, 3) }} {{ $item->material->unit }}
                                @if($item->adjustment_reason)
                                    <br><small>{{ $item->adjustment_reason }}</small>
                                @endif
                            </td>
                            <td class="{{ !$record->supervisor_confirmed_at ? 'resin-usage-unconfirmed' : '' }}">
                                @if($record->supervisor_confirmed_at)
                                    {{ $record->supervisor_name }}<br>
                                    <small>{{ $record->supervisor_confirmed_at->format('Y-m-d H:i') }}</small>
                                @else
                                    未確認
                                @endif
                            </td>
                            @if($loop->first)
                                <td rowspan="{{ $record->items->count() }}">
                                    <a href="{{ route('resin-usage.edit', $record) }}" class="action-link">修正</a>
                                    <form action="{{ route('resin-usage.destroy', $record) }}" method="POST" class="delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-link action-link-danger" onclick="return confirm('この日のレジン使用量記録を削除しますか？');">削除</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $records->links() }}
@endif

<section class="resin-usage-audit">
    <h2>修正・削除の履歴</h2>
    @if($audits->isEmpty())
        <p>修正・削除の履歴はありません。</p>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>対象日</th>
                        <th>操作</th>
                        <th>実行者</th>
                        <th>操作日時</th>
                        <th>変更内容</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($audits as $audit)
                        <tr>
                            <td>{{ $audit->usage_date->format('Y-m-d') }}</td>
                            <td>{{ $audit->action === 'deleted' ? '削除' : '修正' }}</td>
                            <td>{{ $audit->changed_by_name }}</td>
                            <td>{{ $audit->created_at->format('Y-m-d H:i:s') }}</td>
                            <td>
                                <details>
                                    <summary>変更前後を表示</summary>
                                    @foreach(['変更前' => $audit->before_data, '変更後' => $audit->after_data] as $label => $snapshot)
                                        @if($snapshot)
                                            <h4>{{ $label }}</h4>
                                            <p>
                                                確認者：{{ $snapshot['operator_name'] }}<br>
                                                上司確認：
                                                {{ $snapshot['supervisor_confirmed_at'] ? $snapshot['supervisor_name'] . '（' . $snapshot['supervisor_confirmed_at'] . '）' : '未確認' }}
                                            </p>
                                            <div class="table-responsive">
                                                <table>
                                                    <thead>
                                                        <tr>
                                                            <th>レジン</th>
                                                            <th>前回在庫</th>
                                                            <th>使用量</th>
                                                            <th>補充数</th>
                                                            <th>計算在庫</th>
                                                            <th>実在庫</th>
                                                            <th>差異</th>
                                                            <th>差異の理由</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($snapshot['items'] as $snapshotItem)
                                                            <tr>
                                                                <td>{{ $snapshotItem['material'] }}</td>
                                                                <td>{{ number_format($snapshotItem['opening_stock'], 3) }} {{ $snapshotItem['unit'] }}</td>
                                                                <td>{{ number_format($snapshotItem['used_quantity'], 3) }} {{ $snapshotItem['unit'] }}</td>
                                                                <td>{{ number_format($snapshotItem['replenished_quantity'], 3) }} {{ $snapshotItem['unit'] }}</td>
                                                                <td>{{ number_format($snapshotItem['calculated_stock'], 3) }} {{ $snapshotItem['unit'] }}</td>
                                                                <td>{{ number_format($snapshotItem['actual_stock'], 3) }} {{ $snapshotItem['unit'] }}</td>
                                                                <td>{{ $snapshotItem['adjustment_quantity'] > 0 ? '+' : '' }}{{ number_format($snapshotItem['adjustment_quantity'], 3) }} {{ $snapshotItem['unit'] }}</td>
                                                                <td>{{ $snapshotItem['adjustment_reason'] ?: '—' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    @endforeach
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $audits->links() }}
    @endif
</section>
@endsection
