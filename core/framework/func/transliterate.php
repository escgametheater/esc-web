<?php
declare(encoding='UTF-8');

function transliterate($source)
{
    static $to_replace = [
        'きゃ', 'きゅ', 'きょ',
        'しゃ', 'しゅ', 'しょ',
        'ちゃ', 'ちゅ', 'ちょ',
        'にゃ', 'にゅ', 'にょ',
        'ひゃ', 'ひゅ', 'ひょ',
        'みゃ', 'みゅ', 'みょ',
        'りゃ', 'りゅ', 'りょ',

        'ぎゃ', 'ぎゅ', 'ぎょ',
        'じゃ', 'じゅ', 'じょ',
        'ぢゃ', 'ぢゅ', 'ぢょ',
        'びゃ', 'びゅ', 'びょ',
        'ぴゃ', 'ぴゅ', 'ぴょ',

        'あ', 'い', 'う', 'え', 'お',
        'か', 'き', 'く', 'け', 'こ',
        'さ', 'し', 'す', 'せ', 'そ',
        'た', 'ち', 'つ', 'て', 'と',
        'な', 'に', 'ぬ', 'ね', 'の',
        'は', 'ひ', 'ふ', 'へ', 'ほ',
        'ま', 'み', 'む', 'め', 'も',
        'や',       'ゆ',      'よ',
        'ら', 'り', 'る', 'れ', 'ろ',
        'わ', 'ゐ',      'ゑ', 'を',
                              'ん',

        'が', 'ぎ', 'ぐ', 'げ', 'ご',
        'ざ', 'じ', 'ず', 'ぜ', 'ぞ',
        'だ', 'ぢ', 'づ', 'で', 'ど',
        'ば', 'び', 'ぶ', 'べ', 'ぼ',
        'ぱ', 'ぴ', 'ぷ', 'ぺ', 'ぽ',
    ];
    static $replace_by = [
        'kya', 'kyu', 'kyo',
        'sha', 'shu', 'sho',
        'cha', 'chu', 'cho',
        'nya', 'nyu', 'nyo',
        'hya', 'hyu', 'hyo',
        'mya', 'myu', 'myo',
        'rya', 'ryu', 'ryo',

        'gya', 'gyu', 'gyo',
        'ja',  'ju',  'jo',
        'ja',  'ju',  'jo',
        'bya', 'byu', 'byo',
        'pya', 'pyu', 'pyo',

        'a',  'i',   'u',   'e',  'o',
        'ka', 'ki',  'ku',  'ke', 'ko',
        'sa', 'shi', 'su',  'se', 'so',
        'ta', 'chi', 'tsu', 'te', 'to',
        'na', 'ni',  'nu',  'ne', 'no',
        'ha', 'hi',  'fu',  'he', 'ho',
        'ma', 'mi',  'mu',  'me', 'mo',
        'ya',        'yu',        'yo',
        'ra', 'ri',  'ru',  're', 'ro',
        'wa', 'wi',         'we', 'wo',
                                  'n',
        'ga', 'gi',  'gu',  'ge', 'go',
        'za', 'ji',  'zu',  'ze', 'zo',
        'da', 'ji',  'zu',  'de', 'do',
        'ba', 'bi',  'bu',  'be', 'bo',
        'pa', 'pi',  'pu',  'pe', 'po',
    ];
    return str_replace($to_replace, $replace_by, $source);
}