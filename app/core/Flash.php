<?php

class Flash
{
    public static function add(string $type, string $message): void
    {
        $bag = Session::get('_flash', []);
        $bag[] = ['type' => $type, 'message' => $message];
        Session::set('_flash', $bag);
    }

    public static function success(string $message): void { self::add('success', $message); }
    public static function error(string $message): void   { self::add('error', $message); }
    public static function info(string $message): void    { self::add('info', $message); }
    public static function warning(string $message): void { self::add('warning', $message); }

    public static function pull(): array
    {
        $bag = Session::get('_flash', []);
        Session::forget('_flash');
        return is_array($bag) ? $bag : [];
    }
}
