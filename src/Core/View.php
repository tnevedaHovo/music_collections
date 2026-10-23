<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Renderiza um template dentro do layout. */
    public static function page(string $template, array $vars = [], string $title = '', string $nav = ''): void
    {
        $content = self::partial($template, $vars);
        echo self::partial('layout', ['content' => $content, 'title' => $title, 'nav' => $nav]);
    }

    public static function partial(string $template, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require APP_ROOT . '/templates/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
