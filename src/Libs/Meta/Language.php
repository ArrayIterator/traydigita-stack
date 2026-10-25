<?php
/** @noinspection SpellCheckingInspection */
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Meta;

use function is_string;
use function strtolower;
use function trim;

class Language
{
    /**
     * Default language code
     */
    public const DEFAULT_LANGUAGE = 'en_US';

    /**
     * @var ?array{
     *     code: string,
     *     english_name: string,
     *     native_name: string,
     *     iso: string[],
     * }
     */
    public readonly ?array $language;

    /**
     * Language constructor.
     *
     * @param string $locale
     */
    public function __construct(public readonly string $locale = self::DEFAULT_LANGUAGE)
    {
        $this->language = self::get($locale);
    }

    /**
     * @param key-of<self::LANGUAGE_LISTS>|(key-of<self::LANGUAGE_LISTS>&lowercase-string)|string $name
     * @return ?array{
     *     code: string,
     *     english_name: string,
     *     native_name: string,
     *     iso: string[],
     * }
     */
    public static function get(mixed $name): ?array
    {
        if (!is_string($name)) {
            return null;
        }
        $name = strtolower(trim($name));
        $definition = self::LANGUAGE_LISTS[$name] ?? null;
        if (!$definition) {
            return null;
        }
        return $definition;
    }

    /**
     * Get the language code for a given language name.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public static function code(mixed $name, string $default = 'en_US'): string
    {
        $lang = self::get($name);
        if ($lang && isset($lang['code'])) {
            return $lang['code'];
        }
        return $default;
    }

    /**
     * @var array<string, array{
     *     code: string,
     *     english_name: string,
     *     native_name: string,
     *     iso: string[],
     * }>
     */
    public const LANGUAGE_LISTS = [
        'af' => [
            'code' => 'af',
            'english_name' => 'Afrikaans',
            'native_name' => 'Afrikaans',
            'iso' => [
                'af',
                'afr',
            ],
        ],
        'am' => [
            'code' => 'am',
            'english_name' => 'Amharic',
            'native_name' => 'አማርኛ',
            'iso' => [
                'am',
                'amh',
            ],
        ],
        'arg' => [
            'code' => 'arg',
            'english_name' => 'Aragonese',
            'native_name' => 'Aragonés',
            'iso' => [
                'an',
                'arg',
                'arg',
            ],
        ],
        'ar' => [
            'code' => 'ar',
            'english_name' => 'Arabic',
            'native_name' => 'العربية',
            'iso' => [
                'ar',
                'ara',
            ],
        ],
        'ary' => [
            'code' => 'ary',
            'english_name' => 'Moroccan Arabic',
            'native_name' => 'العربية المغربية',
            'iso' => [
                'ar',
                'ary',
            ],
        ],
        'as' => [
            'code' => 'as',
            'english_name' => 'Assamese',
            'native_name' => 'অসমীয়া',
            'iso' => [
                'as',
                'asm',
                'asm',
            ],
        ],
        'azb' => [
            'code' => 'azb',
            'english_name' => 'South Azerbaijani',
            'native_name' => 'گؤنئی آذربایجان',
            'iso' => [
                'az',
                'azb',
            ],
        ],
        'az' => [
            'code' => 'az',
            'english_name' => 'Azerbaijani',
            'native_name' => 'Azərbaycan dili',
            'iso' => [
                'az',
                'aze',
            ],
        ],
        'bel' => [
            'code' => 'bel',
            'english_name' => 'Belarusian',
            'native_name' => 'Беларуская мова',
            'iso' => [
                'be',
                'bel',
            ],
        ],
        'bg_bg' => [
            'code' => 'bg_BG',
            'english_name' => 'Bulgarian',
            'native_name' => 'Български',
            'iso' => [
                'bg',
                'bul',
            ],
        ],
        'bn_bd' => [
            'code' => 'bn_BD',
            'english_name' => 'Bengali (Bangladesh)',
            'native_name' => 'বাংলা',
            'iso' => [
                'bn',
            ],
        ],
        'bo' => [
            'code' => 'bo',
            'english_name' => 'Tibetan',
            'native_name' => 'བོད་ཡིག',
            'iso' => [
                'bo',
                'tib',
            ],
        ],
        'bs_ba' => [
            'code' => 'bs_BA',
            'english_name' => 'Bosnian',
            'native_name' => 'Bosanski',
            'iso' => [
                'bs',
                'bos',
            ],
        ],
        'ca' => [
            'code' => 'ca',
            'english_name' => 'Catalan',
            'native_name' => 'Català',
            'iso' => [
                'ca',
                'cat',
            ],
        ],
        'ceb' => [
            'code' => 'ceb',
            'english_name' => 'Cebuano',
            'native_name' => 'Cebuano',
            'iso' => [
                'ceb',
                'ceb',
            ],
        ],
        'cs_cz' => [
            'code' => 'cs_CZ',
            'english_name' => 'Czech',
            'native_name' => 'Čeština',
            'iso' => [
                'cs',
                'ces',
            ],
        ],
        'cy' => [
            'code' => 'cy',
            'english_name' => 'Welsh',
            'native_name' => 'Cymraeg',
            'iso' => [
                'cy',
                'cym',
            ],
        ],
        'da_dk' => [
            'code' => 'da_DK',
            'english_name' => 'Danish',
            'native_name' => 'Dansk',
            'iso' => [
                'da',
                'dan',
            ],
        ],
        'de_at' => [
            'code' => 'de_AT',
            'english_name' => 'German (Austria)',
            'native_name' => 'Deutsch (Österreich)',
            'iso' => [
                'de',
            ],
        ],
        'de_de' => [
            'code' => 'de_DE',
            'english_name' => 'German',
            'native_name' => 'Deutsch',
            'iso' => [
                'de',
            ],
        ],
        'de_de_formal' => [
            'code' => 'de_DE_formal',
            'english_name' => 'German (Formal)',
            'native_name' => 'Deutsch (Sie)',
            'iso' => [
                'de',
            ],
        ],
        'de_ch' => [
            'code' => 'de_CH',
            'english_name' => 'German (Switzerland)',
            'native_name' => 'Deutsch (Schweiz)',
            'iso' => [
                'de',
            ],
        ],
        'de_ch_informal' => [
            'code' => 'de_CH_informal',
            'english_name' => 'German (Switzerland, Informal)',
            'native_name' => 'Deutsch (Schweiz, Du)',
            'iso' => [
                'de',
            ],
        ],
        'dsb' => [
            'code' => 'dsb',
            'english_name' => 'Lower Sorbian',
            'native_name' => 'Dolnoserbšćina',
            'iso' => [
                'dsb',
                'dsb',
            ],
        ],
        'dzo' => [
            'code' => 'dzo',
            'english_name' => 'Dzongkha',
            'native_name' => 'རྫོང་ཁ',
            'iso' => [
                'dz',
                'dzo',
            ],
        ],
        'el' => [
            'code' => 'el',
            'english_name' => 'Greek',
            'native_name' => 'Ελληνικά',
            'iso' => [
                'el',
                'ell',
            ],
        ],
        'en_za' => [
            'code' => 'en_ZA',
            'english_name' => 'English (South Africa)',
            'native_name' => 'English (South Africa)',
            'iso' => [
                'en',
                'eng',
                'eng',
            ],
        ],
        'en_ca' => [
            'code' => 'en_CA',
            'english_name' => 'English (Canada)',
            'native_name' => 'English (Canada)',
            'iso' => [
                'en',
                'eng',
                'eng',
            ],
        ],
        'en_nz' => [
            'code' => 'en_NZ',
            'english_name' => 'English (New Zealand)',
            'native_name' => 'English (New Zealand)',
            'iso' => [
                'en',
                'eng',
                'eng',
            ],
        ],
        'en_gb' => [
            'code' => 'en_GB',
            'english_name' => 'English (UK)',
            'native_name' => 'English (UK)',
            'iso' => [
                'en',
                'eng',
                'eng',
            ],
        ],
        'en_au' => [
            'code' => 'en_AU',
            'english_name' => 'English (Australia)',
            'native_name' => 'English (Australia)',
            'iso' => [
                'en',
                'eng',
                'eng',
            ],
        ],
        'en_us' => [
            'code' => 'en_US',
            'english_name' => 'English (United States)',
            'native_name' => 'English (United States)',
            'iso' => [
                'en',
                'eng',
                'eng',
            ],
        ],
        'eo' => [
            'code' => 'eo',
            'english_name' => 'Esperanto',
            'native_name' => 'Esperanto',
            'iso' => [
                'eo',
                'epo',
            ],
        ],
        'es_ar' => [
            'code' => 'es_AR',
            'english_name' => 'Spanish (Argentina)',
            'native_name' => 'Español de Argentina',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_cl' => [
            'code' => 'es_CL',
            'english_name' => 'Spanish (Chile)',
            'native_name' => 'Español de Chile',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_co' => [
            'code' => 'es_CO',
            'english_name' => 'Spanish (Colombia)',
            'native_name' => 'Español de Colombia',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_cr' => [
            'code' => 'es_CR',
            'english_name' => 'Spanish (Costa Rica)',
            'native_name' => 'Español de Costa Rica',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_mx' => [
            'code' => 'es_MX',
            'english_name' => 'Spanish (Mexico)',
            'native_name' => 'Español de México',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_es' => [
            'code' => 'es_ES',
            'english_name' => 'Spanish (Spain)',
            'native_name' => 'Español',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_pe' => [
            'code' => 'es_PE',
            'english_name' => 'Spanish (Peru)',
            'native_name' => 'Español de Perú',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_ve' => [
            'code' => 'es_VE',
            'english_name' => 'Spanish (Venezuela)',
            'native_name' => 'Español de Venezuela',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_ec' => [
            'code' => 'es_EC',
            'english_name' => 'Spanish (Ecuador)',
            'native_name' => 'Español de Ecuador',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_do' => [
            'code' => 'es_DO',
            'english_name' => 'Spanish (Dominican Republic)',
            'native_name' => 'Español de República Dominicana',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_uy' => [
            'code' => 'es_UY',
            'english_name' => 'Spanish (Uruguay)',
            'native_name' => 'Español de Uruguay',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_pr' => [
            'code' => 'es_PR',
            'english_name' => 'Spanish (Puerto Rico)',
            'native_name' => 'Español de Puerto Rico',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'es_gt' => [
            'code' => 'es_GT',
            'english_name' => 'Spanish (Guatemala)',
            'native_name' => 'Español de Guatemala',
            'iso' => [
                'es',
                'spa',
                'spa',
            ],
        ],
        'et' => [
            'code' => 'et',
            'english_name' => 'Estonian',
            'native_name' => 'Eesti',
            'iso' => [
                'et',
                'est',
            ],
        ],
        'eu' => [
            'code' => 'eu',
            'english_name' => 'Basque',
            'native_name' => 'Euskara',
            'iso' => [
                'eu',
                'eus',
            ],
        ],
        'fa_ir' => [
            'code' => 'fa_IR',
            'english_name' => 'Persian',
            'native_name' => 'فارسی',
            'iso' => [
                'fa',
                'fas',
            ],
        ],
        'fa_af' => [
            'code' => 'fa_AF',
            'english_name' => 'Persian (Afghanistan)',
            'native_name' => '(فارسی (افغانستان',
            'iso' => [
                'fa',
                'fas',
            ],
        ],
        'fi' => [
            'code' => 'fi',
            'english_name' => 'Finnish',
            'native_name' => 'Suomi',
            'iso' => [
                'fi',
                'fin',
            ],
        ],
        'fr_ca' => [
            'code' => 'fr_CA',
            'english_name' => 'French (Canada)',
            'native_name' => 'Français du Canada',
            'iso' => [
                'fr',
                'fra',
            ],
        ],
        'fr_fr' => [
            'code' => 'fr_FR',
            'english_name' => 'French (France)',
            'native_name' => 'Français',
            'iso' => [
                'fr',
            ],
        ],
        'fr_be' => [
            'code' => 'fr_BE',
            'english_name' => 'French (Belgium)',
            'native_name' => 'Français de Belgique',
            'iso' => [
                'fr',
                'fra',
            ],
        ],
        'fur' => [
            'code' => 'fur',
            'english_name' => 'Friulian',
            'native_name' => 'Friulian',
            'iso' => [
                'fur',
                'fur',
            ],
        ],
        'fy' => [
            'code' => 'fy',
            'english_name' => 'Frisian',
            'native_name' => 'Frysk',
            'iso' => [
                'fy',
                'fry',
            ],
        ],
        'gd' => [
            'code' => 'gd',
            'english_name' => 'Scottish Gaelic',
            'native_name' => 'Gàidhlig',
            'iso' => [
                'gd',
                'gla',
                'gla',
            ],
        ],
        'gl_es' => [
            'code' => 'gl_ES',
            'english_name' => 'Galician',
            'native_name' => 'Galego',
            'iso' => [
                'gl',
                'glg',
            ],
        ],
        'gu' => [
            'code' => 'gu',
            'english_name' => 'Gujarati',
            'native_name' => 'ગુજરાતી',
            'iso' => [
                'gu',
                'guj',
            ],
        ],
        'haz' => [
            'code' => 'haz',
            'english_name' => 'Hazaragi',
            'native_name' => 'هزاره گی',
            'iso' => [
                'haz',
            ],
        ],
        'he_il' => [
            'code' => 'he_IL',
            'english_name' => 'Hebrew',
            'native_name' => 'עִבְרִית',
            'iso' => [
                'he',
            ],
        ],
        'hi_in' => [
            'code' => 'hi_IN',
            'english_name' => 'Hindi',
            'native_name' => 'हिन्दी',
            'iso' => [
                'hi',
                'hin',
            ],
        ],
        'hr' => [
            'code' => 'hr',
            'english_name' => 'Croatian',
            'native_name' => 'Hrvatski',
            'iso' => [
                'hr',
                'hrv',
            ],
        ],
        'hsb' => [
            'code' => 'hsb',
            'english_name' => 'Upper Sorbian',
            'native_name' => 'Hornjoserbšćina',
            'iso' => [
                'hsb',
                'hsb',
            ],
        ],
        'hu_hu' => [
            'code' => 'hu_HU',
            'english_name' => 'Hungarian',
            'native_name' => 'Magyar',
            'iso' => [
                'hu',
                'hun',
            ],
        ],
        'hy' => [
            'code' => 'hy',
            'english_name' => 'Armenian',
            'native_name' => 'Հայերեն',
            'iso' => [
                'hy',
                'hye',
            ],
        ],
        'id_id' => [
            'code' => 'id_ID',
            'english_name' => 'Indonesian',
            'native_name' => 'Bahasa Indonesia',
            'iso' => [
                'id',
                'ind',
            ],
        ],
        'is_is' => [
            'code' => 'is_IS',
            'english_name' => 'Icelandic',
            'native_name' => 'Íslenska',
            'iso' => [
                'is',
                'isl',
            ],
        ],
        'it_it' => [
            'code' => 'it_IT',
            'english_name' => 'Italian',
            'native_name' => 'Italiano',
            'iso' => [
                'it',
                'ita',
            ],
        ],
        'ja' => [
            'code' => 'ja',
            'english_name' => 'Japanese',
            'native_name' => '日本語',
            'iso' => [
                'ja',
            ],
        ],
        'jv_id' => [
            'code' => 'jv_ID',
            'english_name' => 'Javanese',
            'native_name' => 'Basa Jawa',
            'iso' => [
                'jv',
                'jav',
            ],
        ],
        'ka_ge' => [
            'code' => 'ka_GE',
            'english_name' => 'Georgian',
            'native_name' => 'ქართული',
            'iso' => [
                'ka',
                'kat',
            ],
        ],
        'kab' => [
            'code' => 'kab',
            'english_name' => 'Kabyle',
            'native_name' => 'Taqbaylit',
            'iso' => [
                'kab',
                'kab',
            ],
        ],
        'kk' => [
            'code' => 'kk',
            'english_name' => 'Kazakh',
            'native_name' => 'Қазақ тілі',
            'iso' => [
                'kk',
                'kaz',
            ],
        ],
        'km' => [
            'code' => 'km',
            'english_name' => 'Khmer',
            'native_name' => 'ភាសាខ្មែរ',
            'iso' => [
                'km',
                'khm',
            ],
        ],
        'kn' => [
            'code' => 'kn',
            'english_name' => 'Kannada',
            'native_name' => 'ಕನ್ನಡ',
            'iso' => [
                'kn',
                'kan',
            ],
        ],
        'ko_kr' => [
            'code' => 'ko_KR',
            'english_name' => 'Korean',
            'native_name' => '한국어',
            'iso' => [
                'ko',
                'kor',
            ],
        ],
        'ckb' => [
            'code' => 'ckb',
            'english_name' => 'Kurdish (Sorani)',
            'native_name' => 'كوردی‎',
            'iso' => [
                'ku',
                'ckb',
            ],
        ],
        'kir' => [
            'code' => 'kir',
            'english_name' => 'Kyrgyz',
            'native_name' => 'Кыргызча',
            'iso' => [
                'ky',
                'kir',
                'kir',
            ],
        ],
        'lo' => [
            'code' => 'lo',
            'english_name' => 'Lao',
            'native_name' => 'ພາສາລາວ',
            'iso' => [
                'lo',
                'lao',
            ],
        ],
        'lt_lt' => [
            'code' => 'lt_LT',
            'english_name' => 'Lithuanian',
            'native_name' => 'Lietuvių kalba',
            'iso' => [
                'lt',
                'lit',
            ],
        ],
        'lv' => [
            'code' => 'lv',
            'english_name' => 'Latvian',
            'native_name' => 'Latviešu valoda',
            'iso' => [
                'lv',
                'lav',
            ],
        ],
        'mk_mk' => [
            'code' => 'mk_MK',
            'english_name' => 'Macedonian',
            'native_name' => 'Македонски јазик',
            'iso' => [
                'mk',
                'mkd',
            ],
        ],
        'ml_in' => [
            'code' => 'ml_IN',
            'english_name' => 'Malayalam',
            'native_name' => 'മലയാളം',
            'iso' => [
                'ml',
                'mal',
            ],
        ],
        'mn' => [
            'code' => 'mn',
            'english_name' => 'Mongolian',
            'native_name' => 'Монгол',
            'iso' => [
                'mn',
                'mon',
            ],
        ],
        'mr' => [
            'code' => 'mr',
            'english_name' => 'Marathi',
            'native_name' => 'मराठी',
            'iso' => [
                'mr',
                'mar',
            ],
        ],
        'ms_my' => [
            'code' => 'ms_MY',
            'english_name' => 'Malay',
            'native_name' => 'Bahasa Melayu',
            'iso' => [
                'ms',
                'msa',
            ],
        ],
        'my_mm' => [
            'code' => 'my_MM',
            'english_name' => 'Myanmar (Burmese)',
            'native_name' => 'ဗမာစာ',
            'iso' => [
                'my',
                'mya',
            ],
        ],
        'nb_no' => [
            'code' => 'nb_NO',
            'english_name' => 'Norwegian (Bokmål)',
            'native_name' => 'Norsk bokmål',
            'iso' => [
                'nb',
                'nob',
            ],
        ],
        'ne_np' => [
            'code' => 'ne_NP',
            'english_name' => 'Nepali',
            'native_name' => 'नेपाली',
            'iso' => [
                'ne',
                'nep',
            ],
        ],
        'nl_nl' => [
            'code' => 'nl_NL',
            'english_name' => 'Dutch',
            'native_name' => 'Nederlands',
            'iso' => [
                'nl',
                'nld',
            ],
        ],
        'nl_be' => [
            'code' => 'nl_BE',
            'english_name' => 'Dutch (Belgium)',
            'native_name' => 'Nederlands (België)',
            'iso' => [
                'nl',
                'nld',
            ],
        ],
        'nl_nl_formal' => [
            'code' => 'nl_NL_formal',
            'english_name' => 'Dutch (Formal)',
            'native_name' => 'Nederlands (Formeel)',
            'iso' => [
                'nl',
                'nld',
            ],
        ],
        'nn_no' => [
            'code' => 'nn_NO',
            'english_name' => 'Norwegian (Nynorsk)',
            'native_name' => 'Norsk nynorsk',
            'iso' => [
                'nn',
                'nno',
            ],
        ],
        'oci' => [
            'code' => 'oci',
            'english_name' => 'Occitan',
            'native_name' => 'Occitan',
            'iso' => [
                'oc',
                'oci',
            ],
        ],
        'pa_in' => [
            'code' => 'pa_IN',
            'english_name' => 'Panjabi (India)',
            'native_name' => 'ਪੰਜਾਬੀ',
            'iso' => [
                'pa',
                'pan',
            ],
        ],
        'pl_pl' => [
            'code' => 'pl_PL',
            'english_name' => 'Polish',
            'native_name' => 'Polski',
            'iso' => [
                'pl',
                'pol',
            ],
        ],
        'ps' => [
            'code' => 'ps',
            'english_name' => 'Pashto',
            'native_name' => 'پښتو',
            'iso' => [
                'ps',
                'pus',
            ],
        ],
        'pt_br' => [
            'code' => 'pt_BR',
            'english_name' => 'Portuguese (Brazil)',
            'native_name' => 'Português do Brasil',
            'iso' => [
                'pt',
                'por',
            ],
        ],
        'pt_pt' => [
            'code' => 'pt_PT',
            'english_name' => 'Portuguese (Portugal)',
            'native_name' => 'Português',
            'iso' => [
                'pt',
            ],
        ],
        'pt_ao' => [
            'code' => 'pt_AO',
            'english_name' => 'Portuguese (Angola)',
            'native_name' => 'Português de Angola',
            'iso' => [
                'pt',
            ],
        ],
        'pt_pt_ao90' => [
            'code' => 'pt_PT_ao90',
            'english_name' => 'Portuguese (Portugal, AO90)',
            'native_name' => 'Português (AO90)',
            'iso' => [
                'pt',
            ],
        ],
        'rhg' => [
            'code' => 'rhg',
            'english_name' => 'Rohingya',
            'native_name' => 'Ruáinga',
            'iso' => [
                'rhg',
            ],
        ],
        'ro_ro' => [
            'code' => 'ro_RO',
            'english_name' => 'Romanian',
            'native_name' => 'Română',
            'iso' => [
                'ro',
                'ron',
            ],
        ],
        'ru_ru' => [
            'code' => 'ru_RU',
            'english_name' => 'Russian',
            'native_name' => 'Русский',
            'iso' => [
                'ru',
                'rus',
            ],
        ],
        'sah' => [
            'code' => 'sah',
            'english_name' => 'Sakha',
            'native_name' => 'Сахалыы',
            'iso' => [
                'sah',
                'sah',
            ],
        ],
        'snd' => [
            'code' => 'snd',
            'english_name' => 'Sindhi',
            'native_name' => 'سنڌي',
            'iso' => [
                'sd',
                'snd',
                'snd',
            ],
        ],
        'si_lk' => [
            'code' => 'si_LK',
            'english_name' => 'Sinhala',
            'native_name' => 'සිංහල',
            'iso' => [
                'si',
                'sin',
            ],
        ],
        'sk_sk' => [
            'code' => 'sk_SK',
            'english_name' => 'Slovak',
            'native_name' => 'Slovenčina',
            'iso' => [
                'sk',
                'slk',
            ],
        ],
        'skr' => [
            'code' => 'skr',
            'english_name' => 'Saraiki',
            'native_name' => 'سرائیکی',
            'iso' => [
                'skr',
            ],
        ],
        'sl_si' => [
            'code' => 'sl_SI',
            'english_name' => 'Slovenian',
            'native_name' => 'Slovenščina',
            'iso' => [
                'sl',
                'slv',
            ],
        ],
        'sq' => [
            'code' => 'sq',
            'english_name' => 'Albanian',
            'native_name' => 'Shqip',
            'iso' => [
                'sq',
                'sqi',
            ],
        ],
        'sr_rs' => [
            'code' => 'sr_RS',
            'english_name' => 'Serbian',
            'native_name' => 'Српски језик',
            'iso' => [
                'sr',
                'srp',
            ],
        ],
        'sv_se' => [
            'code' => 'sv_SE',
            'english_name' => 'Swedish',
            'native_name' => 'Svenska',
            'iso' => [
                'sv',
                'swe',
            ],
        ],
        'sw' => [
            'code' => 'sw',
            'english_name' => 'Swahili',
            'native_name' => 'Kiswahili',
            'iso' => [
                'sw',
                'swa',
            ],
        ],
        'szl' => [
            'code' => 'szl',
            'english_name' => 'Silesian',
            'native_name' => 'Ślōnskŏ gŏdka',
            'iso' => [
                'szl',
            ],
        ],
        'ta_in' => [
            'code' => 'ta_IN',
            'english_name' => 'Tamil',
            'native_name' => 'தமிழ்',
            'iso' => [
                'ta',
                'tam',
            ],
        ],
        'ta_lk' => [
            'code' => 'ta_LK',
            'english_name' => 'Tamil (Sri Lanka)',
            'native_name' => 'தமிழ்',
            'iso' => [
                'ta',
                'tam',
            ],
        ],
        'te' => [
            'code' => 'te',
            'english_name' => 'Telugu',
            'native_name' => 'తెలుగు',
            'iso' => [
                'te',
                'tel',
            ],
        ],
        'th' => [
            'code' => 'th',
            'english_name' => 'Thai',
            'native_name' => 'ไทย',
            'iso' => [
                'th',
                'tha',
            ],
        ],
        'tl' => [
            'code' => 'tl',
            'english_name' => 'Tagalog',
            'native_name' => 'Tagalog',
            'iso' => [
                'tl',
                'tgl',
            ],
        ],
        'tr_tr' => [
            'code' => 'tr_TR',
            'english_name' => 'Turkish',
            'native_name' => 'Türkçe',
            'iso' => [
                'tr',
                'tur',
            ],
        ],
        'tt_ru' => [
            'code' => 'tt_RU',
            'english_name' => 'Tatar',
            'native_name' => 'Татар теле',
            'iso' => [
                'tt',
                'tat',
            ],
        ],
        'tah' => [
            'code' => 'tah',
            'english_name' => 'Tahitian',
            'native_name' => 'Reo Tahiti',
            'iso' => [
                'ty',
                'tah',
                'tah',
            ],
        ],
        'ug_cn' => [
            'code' => 'ug_CN',
            'english_name' => 'Uighur',
            'native_name' => 'ئۇيغۇرچە',
            'iso' => [
                'ug',
                'uig',
            ],
        ],
        'uk' => [
            'code' => 'uk',
            'english_name' => 'Ukrainian',
            'native_name' => 'Українська',
            'iso' => [
                'uk',
                'ukr',
            ],
        ],
        'ur' => [
            'code' => 'ur',
            'english_name' => 'Urdu',
            'native_name' => 'اردو',
            'iso' => [
                'ur',
                'urd',
            ],
        ],
        'uz_uz' => [
            'code' => 'uz_UZ',
            'english_name' => 'Uzbek',
            'native_name' => 'O‘zbekcha',
            'iso' => [
                'uz',
                'uzb',
            ],
        ],
        'vi' => [
            'code' => 'vi',
            'english_name' => 'Vietnamese',
            'native_name' => 'Tiếng Việt',
            'iso' => [
                'vi',
                'vie',
            ],
        ],
        'yor' => [
            'code' => 'yor',
            'english_name' => 'Yoruba',
            'native_name' => 'Yorùbá',
            'iso' => [
                'yo',
                'yor',
                'yor',
            ],
        ],
        'zh_tw' => [
            'code' => 'zh_TW',
            'english_name' => 'Chinese (Taiwan)',
            'native_name' => '繁體中文',
            'iso' => [
                'zh',
                'zho',
            ],
        ],
        'zh_hk' => [
            'code' => 'zh_HK',
            'english_name' => 'Chinese (Hong Kong)',
            'native_name' => '香港中文',
            'iso' => [
                'zh',
                'zho',
            ],
        ],
        'zh_cn' => [
            'code' => 'zh_CN',
            'english_name' => 'Chinese (China)',
            'native_name' => '简体中文',
            'iso' => [
                'zh',
                'zho',
            ],
        ],
    ];
}
