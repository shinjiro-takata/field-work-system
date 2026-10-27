{{-- レシピ外の材料入力欄。$selectableMaterials: 選択可能な材料、$extraRows: 既存の入力行 [['material_id' => , 'quantity' => ]] --}}
<div class="materials-section extra-materials">
    <h3>レシピ外の材料（使用した場合のみ）</h3>
    <p class="extra-materials__note">レシピにない材料を使った場合は追加し、備考に理由を入力してください。</p>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.getElementById('extra-materials-rows');
        const template = document.getElementById('extra-row-template');
        let nextIndex = {{ count($extraRows) }};
        document.getElementById('extra-row-add').addEventListener('click', function() {
            rows.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, nextIndex++));
        });
        rows.addEventListener('click', function(e) {
            if (e.target.classList.contains('extra-row-remove')) {
                e.target.closest('.extra-row').remove();
            }
        });
    });
</script>
