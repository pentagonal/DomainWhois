<?php
/**
 * This package contains some code that reused by other repository(es) for private uses.
 * But on some certain conditions, it will also allowed to used as commercials project.
 * Some code & coding standard also used from other repositories as inspiration ideas.
 * And also uses 3rd-Party as to be used as result value without their permission but permit to be used.
 *
 * @license GPL-3.0  {@link https://www.gnu.org/licenses/gpl-3.0.en.html}
 * @copyright (c) 2017. Pentagonal Development
 * @author pentagonal <org@pentagonal.org>
 */

namespace Pentagonal\WhoIs;

use Pentagonal\WhoIs\Util\DataGetter;

/**
 * Class Verifier
 * @package Pentagonal\Whois
 */
class Verifier
{
    const IPV4_REGEX = '~^
        # start with 0. to 255.
        (?:0|[1-9][0-9]?|1[0-9]{0,2}|2[0-5]{0,2})
        # start with 0. to 255. 3 times
        (?:\.(?:0|[1-9][0-9]?|1[0-9]{0,2}|2[0-5]{0,2})){3}
    $~x';

    const IPV4_LOCAL_REGEX = '~^
        (?:
            (?:
                1?0 | # start with 0. or 10.
                127  # start with 127.
            )\.(?:0|1[0-9]{0,2}|2[0-5]{0,2}) # next 0 to 255
            | 192\.168
            | 172\.16
        )
        # next 0. to 255. twice
        (?:
            \.(?:0|1[0-9]{0,2}|2[0-5]{0,2})
        ){2}
    $~x';

    /**
     * Regex Global Domain
     */
    const REGEX_GLOBAL = '/[^a-z0-9\-\P{Latin}\P{Hebrew}\P{Greek}\P{Cyrillic}
        \P{Han}\P{Arabic}\P{Gujarati}\P{Armenian}\P{Hiragana}\P{Thai}]/x';

    /**
     * @var array
     */
    protected $regexExtension = [
        "com" => self::REGEX_GLOBAL,
        "net" => self::REGEX_GLOBAL
    ];

    /**
     * @var array
     * just add common to prevent spam
     */
    protected $commonEmailProvider = [
        'gmail', 'hotmail', 'outlook', 'yahoo',
        'mail', 'gmx', 'inbox', 'rocketmail',
        'hushmail', 'null', 'hush',
        'hackrmail', 'yandex'
    ];

    const SELECTOR_EMAIL_NAME      = 'email_name';
    const SELECTOR_FULL_NAME       = 'domain_name';
    const SELECTOR_DOMAIN_NAME     = 'domain_name_base';
    const SELECTOR_EXTENSION_NAME  = 'domain_extension';
    const SELECTOR_SUB_DOMAIN_NAME = 'sub_domain';

    /**
     * @var DataGetter
     */
    protected $dataGetter;

    /**
     * Verifier constructor.
     *
     * @param DataGetter $dataGetter
     */
    public function __construct(DataGetter $dataGetter)
    {
        $this->dataGetter = $dataGetter;
    }

    /**
     * @return DataGetter
     */
    public function getDataGetter()
    {
        return $this->dataGetter;
    }

    /**
     * @return array
     */
    public function getExtensionList()
    {
        return $this->dataGetter->getTLDList();
    }

    /**
     * @param string $string
     * @return bool|string
     */
    public function getExtensionIDN($string)
    {
        if (!is_string($string) || strlen(trim($string)) < 2) {
            return false;
        }

        $string  = strtolower(trim($string));
        $string  = idn_to_ascii($string);
        return isset($this->getExtensionList()[$string])
                ? idn_to_utf8($string)
                : false;
    }

    /**
     * @param string $string
     * @return bool
     */
    public function isExtensionExist($string)
    {
        return $this->getExtensionIDN($string) !== false;
    }

    /**
     * @param string $domainName
     * @return array|bool
     */
    public function validateDomain($domainName)
    {
        if (! is_string($domainName)
            || trim($domainName) === ''
            || strlen($domainName) > 255
            || ! strpos($domainName, '.')
            || preg_match('/(?:^[\-\.])|[~!@#$%^&*()+`=\\|\'{}\[\\];":,\/<>?\s]|[\-]\.|\.\.|(?:[-.]$)/', $domainName)
        ) {
            return false;
        }

        $domainName = strtolower($domainName);
        $result = [
            self::SELECTOR_FULL_NAME  => $domainName,
            self::SELECTOR_SUB_DOMAIN_NAME => null,
            self::SELECTOR_DOMAIN_NAME => null,
            self::SELECTOR_EXTENSION_NAME => null,
        ];

        $arrayDomain = explode('.', $domainName);
        $arrayDomainLength = count($arrayDomain);
        $result[self::SELECTOR_EXTENSION_NAME] = array_pop($arrayDomain);
        $result[self::SELECTOR_DOMAIN_NAME]    = array_pop($arrayDomain);
        $extension = idn_to_ascii($result[self::SELECTOR_EXTENSION_NAME]);

        if (!isset($this->getExtensionList()[$extension])
            || $result[self::SELECTOR_DOMAIN_NAME] > 63
            /* just make sure example.(com|org) not used */
            /* || result[1] === 'example' && ['com', 'org'].indexOf(result.extension_domain) > -1 */
        ) {
            return false;
        }

        if (isset($this->regexExtension[$extension])
            && @preg_match(
                preg_replace('/\s*/', '', $this->regexExtension[$extension]),
                $result[self::SELECTOR_DOMAIN_NAME],
                $match,
                PREG_NO_ERROR
            )
        ) {
            return false;
        } elseif (!preg_match('/^[a-z0-9]+(?:(?:[a-z0-9-]+)?[a-z0-9]$)?/', $result[self::SELECTOR_DOMAIN_NAME])) {
            return false;
        }

        if ($arrayDomainLength > 2) {
            $result[self::SELECTOR_SUB_DOMAIN_NAME] = implode('.', $arrayDomain);
        }
        $result[self::SELECTOR_FULL_NAME] = $result[self::SELECTOR_SUB_DOMAIN_NAME]
                                            . $result[self::SELECTOR_DOMAIN_NAME];
        return $result;
    }

    /**
     * @param string $domainName
     * @return bool
     */
    public function isDomain($domainName)
    {
        return is_array($this->validateDomain($domainName));
    }

    /**
     * @param string $domainName
     * @return bool|array
     */
    public function validateTopDomain($domainName)
    {
        $domain = $this->validateDomain($domainName);
        if (is_array($domain) && (
                empty($domain[self::SELECTOR_SUB_DOMAIN_NAME])
                || strpos($domain[self::SELECTOR_SUB_DOMAIN_NAME], '.') === false
                   && in_array(
                       $domain[self::SELECTOR_DOMAIN_NAME],
                       $this->getExtensionList()[$domain[self::SELECTOR_EXTENSION_NAME]]
                   )
            )
        ) {
            return $domain;
        }

        return false;
    }

    /**
     * @param string $domainName
     * @return bool
     */
    public function isTopDomain($domainName)
    {
        return is_array($this->validateTopDomain($domainName));
    }

    /**
     * @param string $email
     * @return bool|array
     */
    public function validateEmail($email)
    {
        if (!is_string($email) || strlen(trim($email)) < 6 || substr_count($email, '@') <> 1
            || stripos($email, '.') === false
        ) {
            return false;
        }

        $email = trim(strtolower($email));
        if (preg_match('/(?:^@)|(?:@$)/', $email, $match)) {
            return false;
        }

        $emailArray = explode('@', $email);
        if (count($emailArray) <> 2 || (!$domainArray = $this->validateDomain($emailArray[1]))) {
            return false;
        }
        // sanity on global domains
        if (in_array($domainArray[self::SELECTOR_EXTENSION_NAME], ['de', 'ru', 'co', 'net', 'com'])) {
            if (! empty($domainArray[self::SELECTOR_SUB_DOMAIN_NAME])) {
                return in_array($domainArray[self::SELECTOR_DOMAIN_NAME], $this->commonEmailProvider);
            }
        }

        if (strlen($emailArray[0]) > 254
            /**
             * for standard usage email address only contains:
             * alphabetical & underscore (_) dash (-) and dotted (.)
             */
            || preg_match('/[^a-z0-9_\-.]/', $emailArray[0])
            || preg_match('/(?:\.\.)|(?:^[-_])|(?:[-_]$)/', $emailArray[0])
            /**
             * Could not contain non alphabetical or numeric on start or end of email address
             */
            || ! preg_match('/^[a-z0-9]/', $emailArray[0])
            || ! preg_match('/[a-z0-9]$/', $emailArray[0])
        ) {
            return false;
        }
        return array_merge(
            [self::SELECTOR_EMAIL_NAME => $emailArray[0]],
            $domainArray
        );
    }

    /**
     * @param string $email
     * @return bool
     */
    public function isEmail($email)
    {
        return is_array($this->validateEmail($email));
    }

    /**
     * @param string $email
     *
     * @return bool|string
     */
    public function sanitizeEmail($email)
    {
        $email = $this->validateEmail($email);
        if (is_array($email)) {
            return "{$email[self::SELECTOR_EMAIL_NAME]}@"
                   ."{$email[self::SELECTOR_FULL_NAME]}.{$email[self::SELECTOR_EXTENSION_NAME]}";
        }

        return false;
    }

    /**
     * @param string $domain
     *
     * @return bool|string
     */
    public function sanitizeDomain($domain)
    {
        $domain = $this->validateDomain($domain);
        if (is_array($domain)) {
            return "{$domain[self::SELECTOR_FULL_NAME]}.{$domain[self::SELECTOR_EXTENSION_NAME]}";
        }

        return false;
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function isIPv4($ip)
    {
        if (strlen($ip) > 15) {
            return false;
        }
        return (bool) preg_match(
            self::IPV4_REGEX,
            $ip
        );
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function isLocalIPv4($ip)
    {
        if (strlen($ip) > 15) {
            return false;
        }

        return (bool) preg_match(
            self::IPV4_LOCAL_REGEX,
            $ip
        );
    }

    /**
     * @param string|int $name
     * @return bool|string
     */
    public function validateASN($name)
    {
        if (!is_numeric($name) && is_string($name)) {
            return false;
        }

        if (preg_match('/^(?:AS)?(?P<number>[0-9]{1,7})$/i', $name, $match)) {
            return $match['number'];
        }

        return false;
    }
}
