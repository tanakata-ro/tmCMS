# tmCMS テーマ・UIカスタマイズマニュアル

## 目次
1. [テーマの切り替え方](#テーマの切り替え方)
2. [同梱テーマ一覧](#同梱テーマ一覧)
3. [テーマのフォルダ構成](#テーマのフォルダ構成)
4. [フロントテーマの作り方](#フロントテーマの作り方)
5. [管理画面テーマの作り方](#管理画面テーマの作り方)
6. [テンプレート変数リファレンス](#テンプレート変数リファレンス)
7. [テーマの配布・インポート方法](#テーマの配布インポート方法)
8. [CSSだけ変える簡単カスタマイズ](#cssだけ変える簡単カスタマイズ)

---

## テーマの切り替え方

管理画面 → **テーマ管理** → 使いたいテーマの「**有効化**」ボタンを押す。

---

## 同梱テーマ一覧

| テーマ名 | 説明 | 管理画面テーマ |
|---------|------|:---:|
| `default` | 白黒マテリアルデザイン（標準） | あり |
| `dark` | ダークモード | — |
| `minimal` | セリフ体・余白多め・文章重視 | — |
| `news` | 赤・カードグリッド・アイキャッチ大 | — |
| `tech` | Zenn/Qiita風・コード読みやすい | — |
| `magazine` | 深緑・3カラム | — |
| `pastel` | ピンク系・丸角・ブログ向き | — |
| `admin_sidebar` | 左サイドバー型管理テーマ（管理専用） | あり |

---

## テーマのフォルダ構成

```
themes/
└── your-theme/
    ├── theme.json              必須
    ├── style.css               フロントCSS
    ├── admin_style.css         管理画面CSS（任意）
    ├── screenshot.png          プレビュー画像（任意）
    ├── templates/
    │   ├── index.php           記事一覧
    │   ├── article.php         記事詳細
    │   ├── author.php          著者別一覧
    │   ├── header.php          共通ヘッダー（任意）
    │   ├── footer.php          共通フッター（任意）
    │   └── sidebar.php         サイドバー（任意）
    └── admin_templates/
        └── layout.php          管理画面レイアウト（任意）
```

**ポイント:** テンプレートを一部だけ作れば残りは `default` テーマが使われます。

### theme.json

```json
{
    "name": "My Theme",
    "description": "説明文",
    "author": "名前",
    "version": "1.0.0"
}
```

---

## フロントテーマの作り方

### 使えるヘルパー関数

```php
tmcms_e($value)                   // XSSエスケープ（必ず使う）
tmcms_url('public/article.php?id=1') // サイト絶対URL生成
tmcms_header($tmcms)              // header.php を include
tmcms_footer($tmcms)              // footer.php を include
tmcms_sidebar($tmcms)             // sidebar.php を include
tmcms_partial('name', $tmcms)     // {name}.php を include
```

### index.php 最小実装例

```php
<?php tmcms_header($tmcms); ?>

<div style="max-width:800px;margin:2rem auto;padding:0 1rem">
  <?php foreach ($tmcms['posts'] as $post): ?>
  <article style="margin-bottom:2rem">
    <h2>
      <a href="<?= tmcms_url('public/article.php?id='.(int)$post['id']) ?>">
        <?= tmcms_e($post['title']) ?>
      </a>
    </h2>
    <p style="color:#666;font-size:.85rem">
      <?= tmcms_e($post['author_name']) ?>
      · <?= date('Y年m月d日', strtotime($post['updated_at'])) ?>
      · 閲覧 <?= (int)$post['view_count'] ?>
    </p>
    <p><?= tmcms_e($post['preview']) ?></p>
  </article>
  <?php endforeach; ?>
</div>

<?php tmcms_footer($tmcms); ?>
```

### header.php 最小実装例

```php
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= tmcms_e($tmcms['page_title'] ?? $tmcms['site_name']) ?></title>
<link rel="stylesheet" href="<?= tmcms_e($tmcms['theme_url']) ?>/style.css">
<?= $tmcms['head_extra'] ?? '' ?>
</head>
<body>
<header>
  <a href="<?= tmcms_url('public/') ?>"><?= tmcms_e($tmcms['site_name']) ?></a>
</header>
```

### footer.php 最小実装例

```php
<footer>
  <?= tmcms_e($tmcms['site_name']) ?> — Powered by tmCMS
</footer>
</body>
</html>
```

### フッターを画面下部に固定（Sticky Footer）

```css
/* style.css に追加 */
html { height: 100%; }
body { min-height: 100%; display: flex; flex-direction: column; }
body > .site-header { flex-shrink: 0; }
body > .container,
body > .article-wrap { flex: 1 0 auto; }
body > footer { flex-shrink: 0; }
```

---

## 管理画面テーマの作り方

`admin_templates/layout.php` だけ作れば管理画面のHTMLを完全に変えられます。

### layout.php で使える変数

```php
$tmcms['page_title']  // ページタイトル（例: 'ダッシュボード'）
$tmcms['site_name']   // サイト名
$tmcms['content']     // ページのHTML ← ここに出力する
$tmcms['nav_items']   // ナビゲーション項目の配列
$tmcms['admin_css']   // admin_style.css の URL
$tmcms['head_extra']  // <head> に追加するHTML
$tmcms['role']        // ログインユーザーのロール
$tmcms['username']    // ユーザー名
```

### nav_items の構造

```php
foreach ($tmcms['nav_items'] as $item) {
    $item['label']  // 例: 'ダッシュボード'
    $item['href']   // 例: 'dashboard.php'
}
```

### layout.php 実装例

```php
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title><?= Security::e($tmcms['page_title']) ?> — <?= Security::e($tmcms['site_name']) ?></title>
  <link rel="stylesheet" href="<?= Security::e($tmcms['admin_css']) ?>">
  <?= $tmcms['head_extra'] ?? '' ?>
</head>
<body>
<header style="background:#222;color:#fff;padding:.75rem 1.5rem;display:flex;gap:1rem;align-items:center">
  <strong><?= Security::e($tmcms['site_name']) ?></strong>
  <?php foreach ($tmcms['nav_items'] as $item): ?>
  <a href="<?= Security::e($item['href']) ?>" style="color:rgba(255,255,255,.8);text-decoration:none;font-size:.875rem">
    <?= Security::e($item['label']) ?>
  </a>
  <?php endforeach; ?>
  <form method="post" action="logout.php" style="margin-left:auto">
    <input type="hidden" name="_csrf" value="<?= Security::e(Csrf::token()) ?>">
    <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer">ログアウト</button>
  </form>
</header>
<main style="max-width:1140px;margin:1.5rem auto;padding:0 1.25rem">
  <?= $tmcms['content'] ?>
</main>
</body>
</html>
```

---

## テンプレート変数リファレンス

### 全テンプレート共通

| 変数 | 内容 |
|------|------|
| `$tmcms['site_name']` | サイト名 |
| `$tmcms['site_url']` | サイトURL |
| `$tmcms['theme_url']` | テーマディレクトリのURL |
| `$tmcms['tags']` | 全タグ一覧 |
| `$tmcms['head_extra']` | head内追加HTML |

### index.php

| 変数 | 内容 |
|------|------|
| `$tmcms['posts']` | 記事配列 |
| `$tmcms['pagination']` | ページネーション情報 |
| `$tmcms['filter_tag']` | タグ絞り込みID（なければnull） |
| `$tmcms['page_title']` | ページタイトル |

**記事フィールド:**
```
$post['id']              記事ID
$post['title']           タイトル
$post['preview']         本文冒頭（120文字）
$post['author_name']     著者表示名
$post['author_id']       著者ID
$post['updated_at']      更新日時
$post['view_count']      閲覧数
$post['thumbnail_path']  サムネイルURL
```

**ページネーション:**
```
$pg['page']         現在ページ
$pg['total']        総件数
$pg['total_pages']  総ページ数
$pg['base_query']   URLパラメータ（例: "tag_id=3&"）
```

### article.php

```
$tmcms['post']['id']               記事ID
$tmcms['post']['title']            タイトル
$tmcms['post']['html']             本文HTML（そのままecho）
$tmcms['post']['author_name']      著者表示名
$tmcms['post']['author_id']        著者ID
$tmcms['post']['avatar_path']      著者アイコンURL
$tmcms['post']['bio']              著者の一言
$tmcms['post']['tags']             タグ配列
$tmcms['post']['updated_at']       更新日時
$tmcms['post']['view_count']       閲覧数
$tmcms['post']['meta_description'] メタディスクリプション
$tmcms['post']['thumbnail_path']   サムネイルURL
$tmcms['ads']['enabled']           広告有効か
$tmcms['ads']['before_content']    本文前広告HTML
$tmcms['ads']['after_content']     本文後広告HTML
```

### author.php

```
$tmcms['author']['id']           ユーザーID
$tmcms['author']['display_name'] 表示名
$tmcms['author']['bio']          一言
$tmcms['author']['avatar_path']  アイコンURL
$tmcms['posts']                  記事配列（index.phpと同じ）
$tmcms['pagination']             ページネーション
```

---

## テーマの配布・インポート方法

### ZIPでインポート

```bash
zip -r my-theme.zip my-theme/
```

管理画面 → テーマ管理 → 「ZIPからインストール」

### GitHub URLでインポート

管理画面 → テーマ管理 → 「GitHubからインストール」に `https://github.com/user/repo` を入力。

サーバーで `allow_url_fopen` が有効な必要があります。

---

## CSSだけ変える簡単カスタマイズ

### 手順

1. `themes/default/` をコピーして別名に（例: `themes/my-theme/`）
2. `theme.json` の `name` を変更
3. `style.css` を編集
4. 管理画面から有効化

### 配色カスタマイズ例

```css
/* style.cssの先頭に追加するだけ */

/* 暖かみのある配色 */
:root {
  --bg: #fdf6f0;
  --surface: #fffaf6;
  --border: #f0e0d6;
}

/* ブルー系アクセント */
.article__content h2 { border-bottom-color: #1d4ed8; }
.article__tag { background: #eff6ff; color: #1d4ed8; }
```

---

## よくある質問

**Q: テーマを変えたら管理画面も変わりますか？**
→ テーマに `admin_templates/layout.php` がある場合のみ変わります。

**Q: テンプレート内でPHPは使えますか？**
→ 使えます。ユーザーデータの出力は必ず `tmcms_e()` でエスケープしてください。

**Q: `$tmcms['post']['html']` を `tmcms_e()` でエスケープしてよいですか？**
→ してはいけません。`html` はすでにHTML変換済みです。そのまま `echo` してください。
