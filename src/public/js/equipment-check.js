/**
 * サーバー側で描画する設備点検フォームに、簡単な操作補助を追加する。
 */
(function () {
    'use strict';

    // 点検内容の保存中に、フォームが重複送信されるのを防ぐ。
    function protectInspectionForm(form) {
        form.addEventListener('submit', function () {
            var submitButton = form.querySelector('button[type="submit"]');

            if (!submitButton || submitButton.disabled) {
                return;
            }

            submitButton.dataset.originalLabel = submitButton.textContent.trim();
            submitButton.textContent = '保存中...';
            submitButton.disabled = true;
            submitButton.setAttribute('aria-busy', 'true');
        });
    }

    // ブラウザーのキャッシュからフォームへ戻ったとき、保存ボタンを元に戻す。
    function restoreInspectionForms() {
        document.querySelectorAll('[data-inspection-form]').forEach(function (form) {
            var submitButton = form.querySelector('button[type="submit"]');

            if (!submitButton || !submitButton.dataset.originalLabel) {
                return;
            }

            submitButton.textContent = submitButton.dataset.originalLabel;
            submitButton.disabled = false;
            submitButton.removeAttribute('aria-busy');
        });
    }

    // HTMLの読み込み完了後、点検フォームへ送信時の処理を登録する。
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-inspection-form]').forEach(protectInspectionForm);
    });
    window.addEventListener('pageshow', restoreInspectionForms);
}());
