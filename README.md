# tmCMS

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![SQLite](https://img.shields.io/badge/DB-SQLite%20%7C%20MySQL%20%7C%20PostgreSQL-003B57?logo=sqlite&logoColor=white)
![Platform](https://img.shields.io/badge/Platform-Apache-D22128?logo=apache&logoColor=white)

PHP製の軽量CMS。共有レンタルサーバーで動作します。WordPressのような多機能さは求めず、**シンプルに使えること**を目標にしています。

## 動作要件

| 項目 | 要件 | 備考 |
|------|------|------|
| PHP | **8.0 以上** | 8.2 推奨 |
| PHP拡張 | `pdo_sqlite` | MySQL/PG使用時は `pdo_mysql` / `pdo_pgsql` |
| DB | SQLite / MySQL 5.7+ / PostgreSQL 12+ | デフォルトはSQLite（設定不要） |
| Webサーバー | Apache | `.htaccess` / `mod_rewrite` が有効であること |
| ディスク | 10MB以上 | アップロード画像は別途 |


## 主な仕様

### コア

| 項目 | 仕様 |
|------|------|
| 言語 | PHP 8.0+ |
| DB | PDO（SQLite / MySQL / PostgreSQL） |
| セッション | PHP標準セッション |
| 認証 | パスワードハッシュ `PASSWORD_DEFAULT`（bcrypt） |
| CSRF対策 | ワンタイムトークン（有効期限 1時間） |
| ログイン失敗ロック | 5回失敗 → 15分ロック（IPアドレス単位） |
| テーマエンジン | 独自（PHPテンプレート、WordPressに近い方式） |

### 記事・コンテンツ

| 項目 | 仕様 |
|------|------|
| 記法 | Markdown + BBCode（`[size]` `[color]` `[img width=]`） |
| ステータス | 公開 / 下書き / 限定公開 / 予約公開 / 評価依頼 |
| タグ | スラッグ対応（`?tag=php-tips`） |
| 1ページあたりの記事数 | 10件（`config.php` で変更可） |
| 自動保存 | LocalStorage に20秒ごと |

### メディア

| 項目 | 仕様 |
|------|------|
| 対応形式 | JPEG / PNG / GIF / WebP |
| 最大ファイルサイズ | 10MB（`config.php` で変更可） |
| 保存先 | `uploads/u{ユーザーID}/p{記事ID}/` |
| サムネイル生成 | GD拡張が有効な場合のみ自動生成（280×200px） |

### ユーザー

| ロール | 権限 |
|--------|------|
| `admin` | 全権限（ユーザー管理・テーマ管理・広告管理含む） |
| `editor` | 記事の投稿・編集・メディアアップロード |
| `reviewer` | 評価待ち記事の確認のみ |

### テーマ

| 項目 | 仕様 |
|------|------|
| テンプレート言語 | PHP |
| 対象画面 | フロント全ページ + 管理画面（オプション） |
| インポート方法 | ZIPアップロード / GitHub URL |
| 同梱テーマ数 | 8種（default / dark / minimal / news / tech / magazine / pastel / admin_sidebar） |

## 特徴

- **インストール簡単** — ZIPをアップロードしてブラウザからセットアップするだけ
- **DB不要で動く** — デフォルトはSQLite。MySQL / PostgreSQLにも対応
- **テーマシステム** — フロント・管理画面ともにPHPテンプレートで自由にカスタマイズ可能
- **Markdown + BBCode** — 記事はMarkdownとBBCodeで書ける。リアルタイムプレビュー付き
- **メディアライブラリ** — 画像はユーザー別・記事別にフォルダ管理
- **広告設定** — 記事ごとに広告の表示/非表示を切り替え可能
- **著者情報** — 記事末尾に著者のアバター・一言を表示
- **タグURL** — `?tag=php-tips` のきれいなURL

## インストール

1. [Releases](../../releases) から最新の ZIP をダウンロード
2. 解凍して `public_html/` 以下にアップロード（中身のみ）
3. ブラウザで `https://your-domain.com/install/` を開く
4. 画面の指示に従って設定（DB・サイト名・管理者アカウント）
5. インストール完了後、**`install/` フォルダを削除してください**

### 注意点

- `core/`・`db/`・`uploads/` のパーミッションを `755` に設定
- SQLite を選択した場合、DB の設定は不要

## ディレクトリ構成

```
tmcms/
├── admin/          管理画面
├── core/           コアクラス（Auth / DB / CSRF / Theme 等）
├── db/             SQLite DBファイル（Webから直接アクセス不可）
├── docs/           ドキュメント
├── install/        インストーラー（インストール後に削除）
├── public/         フロントエンド（記事一覧・詳細・著者ページ）
├── themes/         テーマ
│   ├── default/        白黒マテリアルデザイン（標準）
│   ├── dark/           ダークモード
└── uploads/        アップロード画像
```

## テーマ

テーマは ZIP でまとめて管理画面からインストール、または GitHub URL を指定してインストールできます。

詳細は [`docs/theme-customization.md`](docs/theme-customization.md) を参照してください。

## ライセンス

[MIT License](LICENSE) © 2026 tanakata-ro