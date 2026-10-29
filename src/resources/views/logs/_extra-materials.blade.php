{{-- レシピ外の材料入力欄（部分ビュー: logs/create と logs/edit から読み込まれる）。
     $selectableMaterials: 選択できる材料（レシピに含まれない材料）、
     $extraRows: 既にある入力行 [['material_id' => 材料ID, 'quantity' => 量]] --}}
<div class="materials-section extra-materials">
    <h3>レシピ外の材料（使用した場合のみ）</h3>
    <p class="extra-materials__note">レシピにない材料を使った場合は追加し、備考に理由を入力してください。</p>
    {{-- 既存の入力行（JavaScript で追加した行もここに並ぶ） --}}
    <div id="extra-materials-rows">
        @foreach($extraRows as $i => $row)
        <div class="material-input-group extra-row">
            <select name="extra_materials[{{ $i }}][material_id]" class="form-select" required>
                <option value="">-- 材料を選択 --</option>
                @foreach($selectableMaterials as $m)
                <option value="{{ $m->id }}" {{ (string) $row['material_id'] === (string) $m->id ? 'selected' : '' }}>{{ $m->name }}（{{ $m->unit }}）</option>
                @endforeach
            </select>
            <input type="number" name="extra_materials[{{ $i }}][quantity]" step="0.001" min="0" value="{{ $row['quantity'] }}" required class="material-input">
            <button type="button" class="btn btn-secondary extra-row-remove">削除</button>
            @error('extra_materials.' . $i . '.material_id')<div class="error">{{ $message }}</div>@enderror
            @error('extra_materials.' . $i . '.quantity')<div class="error">{{ $message }}</div>@enderror
        </div>
        @endforeach
    </div>
    @error('extra_materials')<div class="error">{{ $message }}</div>@enderror
    <button type="button" id="extra-row-add" class="btn btn-secondary">＋ レシピ外の材料を追加</button>

    {{-- 行追加用のひな形。__INDEX__ は JavaScript が連番に置き換える（<template> は画面には表示されない） --}}
    <template id="extra-row-template">
        <div class="material-input-group extra-row">
            <select name="extra_materials[__INDEX__][material_id]" class="form-select" required>
                <option value="">-- 材料を選択 --</option>
                @foreach($selectableMaterials as $m)
                <option value="{{ $m->id }}">{{ $m->name }}（{{ $m->unit }}）</option>
                @endforeach
            </select>
            <input type="number" name="extra_materials[__INDEX__][quantity]" step="0.001" min="0" value="0" required class="material-input">
            <button type="button" class="btn btn-secondary extra-row-remove">削除</button>
        </div>
    </template>
</div>

{{-- JavaScript: レシピ外材料の行の追加・削除 --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.getElementById('extra-materials-rows');
        const template = document.getElementById('extra-row-template');
        // 次に追加する行の番号（name属性の extra_materials[番号] が重複しないよう、既存行数から始める）
        let nextIndex = {{ count($extraRows) }};
        // 「追加」ボタン: ひな形をコピーして番号を埋め込み、末尾に追加する
        document.getElementById('extra-row-add').addEventListener('click', function() {
            rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, nextIndex++));
        });
        // 「削除」ボタン: クリックされた行を取り除く（後から追加した行にも効くよう、親要素で受け取る）
        rows.addEventListener('click', function(e) {
            if (e.target.classList.contains('extra-row-remove')) {
                e.target.closest('.extra-row').remove();
            }
        });
    });
</script>
