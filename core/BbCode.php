<?php
/**
 * BbCode.php - BBCodeをHTMLに変換するクラス
 *
 * 対応タグ:
 *   [size=16px]...[/size]
 *   [color=red]...[/color]  or  [color=#ff0000]...[/color]
 *   [img width=300px]URL[/img]
 *   [card=URL]  OGPリンクカード（フロント側でJS処理）
 */
class BbCode
{
    /**
     * BBCodeをHTMLに変換する
     */
    public static function parse(string $text): string
    {
        // [size=Npx]...[/size]
        $text = preg_replace(
            '/\[size=(\d+)px\](.*?)\[\/size\]/s',
            '<span style="font-size:$1px">$2</span>',
            $text
        );

        // [color=xxx]...[/color]（英語色名 or #rrggbb のみ許可）
        $text = preg_replace(
            '/\[color=([a-zA-Z]+|#[0-9a-fA-F]{3,6})\](.*?)\[\/color\]/s',
            '<span style="color:$1">$2</span>',
            $text
        );

        // [img width=Npx]URL[/img]
        $text = preg_replace_callback(
            '/\[img\s+width=([0-9]+(?:px|%))\](.*?)\[\/img\]/is',
            function (array $m): string {
                $width = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                $url   = htmlspecialchars(trim($m[2]), ENT_QUOTES, 'UTF-8');
                $alt   = htmlspecialchars(basename($url), ENT_QUOTES, 'UTF-8');
                return '<img src="' . $url . '" alt="' . $alt
                    . '" style="width:' . $width . ';max-width:100%;height:auto;border-radius:4px">';
            },
            $text
        );

        return $text;
    }

    /**
     * テキストからBBCodeタグを除去する（プレビュー・検索用）
     */
    public static function strip(string $text): string
    {
        return preg_replace('/\[.*?\]/s', '', $text) ?? $text;
    }
}
