<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Auth::requireLogin();

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
<style>
.doc h2{font-size:1.1rem;font-weight:600;margin:2rem 0 .5rem;padding-bottom:.3rem;border-bottom:2px solid #212121}
.doc h3{font-size:.95rem;font-weight:600;margin:1.25rem 0 .35rem;color:#424242}
.doc p{font-size:.9rem;color:#444;line-height:1.75;margin:.4rem 0}
.doc pre{background:#f5f5f5;padding:1rem;border-radius:4px;font-size:.82rem;overflow-x:auto;margin:.5rem 0}
.doc code{background:#f5f5f5;padding:.1rem .35rem;border-radius:2px;font-size:.82rem}
.doc table{border-collapse:collapse;width:100%;font-size:.875rem;margin:.75rem 0}
.doc th{background:#f5f5f5;padding:.5rem .75rem;border:1px solid #e0e0e0;text-align:left;font-weight:600}
.doc td{padding:.5rem .75rem;border:1px solid #e0e0e0}
</style>

<div class="card elev-1"><div class="card__body doc">
  <h2>Markdown 記法</h2>
  <h3>見出し</h3>
  <pre><code>## 大見出し（H2）
### 中見出し（H3）
#### 小見出し（H4）</code></pre>
  <h3>太字・斜体・取り消し線</h3>
  <pre><code>**太字**  *斜体*  ~~取り消し線~~</code></pre>
  <h3>リスト</h3>
  <pre><code>- 箇条書き
  - 入れ子

1. 番号付き
2. リスト</code></pre>
  <h3>リンク・画像</h3>
  <pre><code>[リンクテキスト](https://example.com)
![alt テキスト](https://example.com/image.jpg)</code></pre>
  <h3>引用</h3>
  <pre><code>&gt; 引用テキスト</code></pre>
  <h3>コードブロック（言語指定でハイライト）</h3>
  <pre><code>```php
echo 'Hello World';
```</code></pre>
  <h3>テーブル</h3>
  <pre><code>| 列1 | 列2 |
|------|------|
| A    | B    |</code></pre>
  <h3>水平線</h3>
  <pre><code>---</code></pre>

  <h2>BBCode 拡張記法</h2>
  <table>
    <thead><tr><th>記法</th><th>説明</th></tr></thead>
    <tbody>
      <tr><td><code>[size=18px]テキスト[/size]</code></td><td>文字サイズ変更</td></tr>
      <tr><td><code>[color=red]テキスト[/color]</code></td><td>文字色変更（英語名 or #rrggbb）</td></tr>
      <tr><td><code>[img width=300px]URL[/img]</code></td><td>幅指定で画像表示</td></tr>
    </tbody>
  </table>

  <h2>画像のアップロード</h2>
  <table>
    <thead><tr><th>方法</th><th>手順</th></tr></thead>
    <tbody>
      <tr><td>ツールバー</td><td>「画像挿入」ボタン → ファイル選択 → 自動挿入</td></tr>
      <tr><td>ドラッグ&amp;ドロップ</td><td>エディタに画像ファイルをドロップ</td></tr>
      <tr><td>メディアライブラリ</td><td>「メディア」ボタン → 一覧から選択</td></tr>
    </tbody>
  </table>

  <h2>公開設定</h2>
  <table>
    <thead><tr><th>設定</th><th>説明</th></tr></thead>
    <tbody>
      <tr><td>下書き</td><td>非公開で保存</td></tr>
      <tr><td>公開</td><td>即時公開</td></tr>
      <tr><td>評価依頼</td><td>レビュアーに確認依頼</td></tr>
      <tr><td>限定公開</td><td>URL＋パスワードを知る人のみ閲覧可</td></tr>
      <tr><td>予約公開</td><td>指定日時に自動公開</td></tr>
    </tbody>
  </table>

  <h2>SEO設定</h2>
  <p><strong>メタディスクリプション:</strong> 検索結果に表示される説明文（120〜160文字推奨）</p>
  <p><strong>サムネイルURL:</strong> SNSシェア時のOGP画像</p>
</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script>hljs.highlightAll();</script>
<?php
Theme::renderAdmin('マニュアル', ob_get_clean());
