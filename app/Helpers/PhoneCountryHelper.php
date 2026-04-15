<?php

namespace App\Helpers;

class PhoneCountryHelper
{
    /**
     * Mapping of calling codes to [iso2, country name, flag emoji].
     * Ordered by longest prefix first for accurate matching.
     */
    public const COUNTRIES = [
        '1' => ['US', 'United States', "\u{1F1FA}\u{1F1F8}"],
        '7' => ['RU', 'Russia', "\u{1F1F7}\u{1F1FA}"],
        '20' => ['EG', 'Egypt', "\u{1F1EA}\u{1F1EC}"],
        '27' => ['ZA', 'South Africa', "\u{1F1FF}\u{1F1E6}"],
        '30' => ['GR', 'Greece', "\u{1F1EC}\u{1F1F7}"],
        '31' => ['NL', 'Netherlands', "\u{1F1F3}\u{1F1F1}"],
        '32' => ['BE', 'Belgium', "\u{1F1E7}\u{1F1EA}"],
        '33' => ['FR', 'France', "\u{1F1EB}\u{1F1F7}"],
        '34' => ['ES', 'Spain', "\u{1F1EA}\u{1F1F8}"],
        '36' => ['HU', 'Hungary', "\u{1F1ED}\u{1F1FA}"],
        '39' => ['IT', 'Italy', "\u{1F1EE}\u{1F1F9}"],
        '40' => ['RO', 'Romania', "\u{1F1F7}\u{1F1F4}"],
        '41' => ['CH', 'Switzerland', "\u{1F1E8}\u{1F1ED}"],
        '43' => ['AT', 'Austria', "\u{1F1E6}\u{1F1F9}"],
        '44' => ['GB', 'United Kingdom', "\u{1F1EC}\u{1F1E7}"],
        '45' => ['DK', 'Denmark', "\u{1F1E9}\u{1F1F0}"],
        '46' => ['SE', 'Sweden', "\u{1F1F8}\u{1F1EA}"],
        '47' => ['NO', 'Norway', "\u{1F1F3}\u{1F1F4}"],
        '48' => ['PL', 'Poland', "\u{1F1F5}\u{1F1F1}"],
        '49' => ['DE', 'Germany', "\u{1F1E9}\u{1F1EA}"],
        '51' => ['PE', 'Peru', "\u{1F1F5}\u{1F1EA}"],
        '52' => ['MX', 'Mexico', "\u{1F1F2}\u{1F1FD}"],
        '53' => ['CU', 'Cuba', "\u{1F1E8}\u{1F1FA}"],
        '54' => ['AR', 'Argentina', "\u{1F1E6}\u{1F1F7}"],
        '55' => ['BR', 'Brazil', "\u{1F1E7}\u{1F1F7}"],
        '56' => ['CL', 'Chile', "\u{1F1E8}\u{1F1F1}"],
        '57' => ['CO', 'Colombia', "\u{1F1E8}\u{1F1F4}"],
        '58' => ['VE', 'Venezuela', "\u{1F1FB}\u{1F1EA}"],
        '60' => ['MY', 'Malaysia', "\u{1F1F2}\u{1F1FE}"],
        '61' => ['AU', 'Australia', "\u{1F1E6}\u{1F1FA}"],
        '62' => ['ID', 'Indonesia', "\u{1F1EE}\u{1F1E9}"],
        '63' => ['PH', 'Philippines', "\u{1F1F5}\u{1F1ED}"],
        '64' => ['NZ', 'New Zealand', "\u{1F1F3}\u{1F1FF}"],
        '65' => ['SG', 'Singapore', "\u{1F1F8}\u{1F1EC}"],
        '66' => ['TH', 'Thailand', "\u{1F1F9}\u{1F1ED}"],
        '81' => ['JP', 'Japan', "\u{1F1EF}\u{1F1F5}"],
        '82' => ['KR', 'South Korea', "\u{1F1F0}\u{1F1F7}"],
        '84' => ['VN', 'Vietnam', "\u{1F1FB}\u{1F1F3}"],
        '86' => ['CN', 'China', "\u{1F1E8}\u{1F1F3}"],
        '90' => ['TR', 'Turkey', "\u{1F1F9}\u{1F1F7}"],
        '91' => ['IN', 'India', "\u{1F1EE}\u{1F1F3}"],
        '92' => ['PK', 'Pakistan', "\u{1F1F5}\u{1F1F0}"],
        '93' => ['AF', 'Afghanistan', "\u{1F1E6}\u{1F1EB}"],
        '94' => ['LK', 'Sri Lanka', "\u{1F1F1}\u{1F1F0}"],
        '95' => ['MM', 'Myanmar', "\u{1F1F2}\u{1F1F2}"],
        '98' => ['IR', 'Iran', "\u{1F1EE}\u{1F1F7}"],
        '212' => ['MA', 'Morocco', "\u{1F1F2}\u{1F1E6}"],
        '213' => ['DZ', 'Algeria', "\u{1F1E9}\u{1F1FF}"],
        '216' => ['TN', 'Tunisia', "\u{1F1F9}\u{1F1F3}"],
        '218' => ['LY', 'Libya', "\u{1F1F1}\u{1F1FE}"],
        '220' => ['GM', 'Gambia', "\u{1F1EC}\u{1F1F2}"],
        '221' => ['SN', 'Senegal', "\u{1F1F8}\u{1F1F3}"],
        '223' => ['ML', 'Mali', "\u{1F1F2}\u{1F1F1}"],
        '224' => ['GN', 'Guinea', "\u{1F1EC}\u{1F1F3}"],
        '225' => ['CI', 'Ivory Coast', "\u{1F1E8}\u{1F1EE}"],
        '226' => ['BF', 'Burkina Faso', "\u{1F1E7}\u{1F1EB}"],
        '227' => ['NE', 'Niger', "\u{1F1F3}\u{1F1EA}"],
        '228' => ['TG', 'Togo', "\u{1F1F9}\u{1F1EC}"],
        '229' => ['BJ', 'Benin', "\u{1F1E7}\u{1F1EF}"],
        '230' => ['MU', 'Mauritius', "\u{1F1F2}\u{1F1FA}"],
        '231' => ['LR', 'Liberia', "\u{1F1F1}\u{1F1F7}"],
        '232' => ['SL', 'Sierra Leone', "\u{1F1F8}\u{1F1F1}"],
        '233' => ['GH', 'Ghana', "\u{1F1EC}\u{1F1ED}"],
        '234' => ['NG', 'Nigeria', "\u{1F1F3}\u{1F1EC}"],
        '235' => ['TD', 'Chad', "\u{1F1F9}\u{1F1E9}"],
        '236' => ['CF', 'Central African Republic', "\u{1F1E8}\u{1F1EB}"],
        '237' => ['CM', 'Cameroon', "\u{1F1E8}\u{1F1F2}"],
        '238' => ['CV', 'Cape Verde', "\u{1F1E8}\u{1F1FB}"],
        '239' => ['ST', 'Sao Tome and Principe', "\u{1F1F8}\u{1F1F9}"],
        '240' => ['GQ', 'Equatorial Guinea', "\u{1F1EC}\u{1F1F6}"],
        '241' => ['GA', 'Gabon', "\u{1F1EC}\u{1F1E6}"],
        '242' => ['CG', 'Congo', "\u{1F1E8}\u{1F1EC}"],
        '243' => ['CD', 'DR Congo', "\u{1F1E8}\u{1F1E9}"],
        '244' => ['AO', 'Angola', "\u{1F1E6}\u{1F1F4}"],
        '245' => ['GW', 'Guinea-Bissau', "\u{1F1EC}\u{1F1FC}"],
        '248' => ['SC', 'Seychelles', "\u{1F1F8}\u{1F1E8}"],
        '249' => ['SD', 'Sudan', "\u{1F1F8}\u{1F1E9}"],
        '250' => ['RW', 'Rwanda', "\u{1F1F7}\u{1F1FC}"],
        '251' => ['ET', 'Ethiopia', "\u{1F1EA}\u{1F1F9}"],
        '252' => ['SO', 'Somalia', "\u{1F1F8}\u{1F1F4}"],
        '253' => ['DJ', 'Djibouti', "\u{1F1E9}\u{1F1EF}"],
        '254' => ['KE', 'Kenya', "\u{1F1F0}\u{1F1EA}"],
        '255' => ['TZ', 'Tanzania', "\u{1F1F9}\u{1F1FF}"],
        '256' => ['UG', 'Uganda', "\u{1F1FA}\u{1F1EC}"],
        '257' => ['BI', 'Burundi', "\u{1F1E7}\u{1F1EE}"],
        '258' => ['MZ', 'Mozambique', "\u{1F1F2}\u{1F1FF}"],
        '260' => ['ZM', 'Zambia', "\u{1F1FF}\u{1F1F2}"],
        '261' => ['MG', 'Madagascar', "\u{1F1F2}\u{1F1EC}"],
        '262' => ['RE', 'Reunion', "\u{1F1F7}\u{1F1EA}"],
        '263' => ['ZW', 'Zimbabwe', "\u{1F1FF}\u{1F1FC}"],
        '264' => ['NA', 'Namibia', "\u{1F1F3}\u{1F1E6}"],
        '265' => ['MW', 'Malawi', "\u{1F1F2}\u{1F1FC}"],
        '266' => ['LS', 'Lesotho', "\u{1F1F1}\u{1F1F8}"],
        '267' => ['BW', 'Botswana', "\u{1F1E7}\u{1F1FC}"],
        '268' => ['SZ', 'Eswatini', "\u{1F1F8}\u{1F1FF}"],
        '269' => ['KM', 'Comoros', "\u{1F1F0}\u{1F1F2}"],
        '291' => ['ER', 'Eritrea', "\u{1F1EA}\u{1F1F7}"],
        '297' => ['AW', 'Aruba', "\u{1F1E6}\u{1F1FC}"],
        '298' => ['FO', 'Faroe Islands', "\u{1F1EB}\u{1F1F4}"],
        '299' => ['GL', 'Greenland', "\u{1F1EC}\u{1F1F1}"],
        '350' => ['GI', 'Gibraltar', "\u{1F1EC}\u{1F1EE}"],
        '351' => ['PT', 'Portugal', "\u{1F1F5}\u{1F1F9}"],
        '352' => ['LU', 'Luxembourg', "\u{1F1F1}\u{1F1FA}"],
        '353' => ['IE', 'Ireland', "\u{1F1EE}\u{1F1EA}"],
        '354' => ['IS', 'Iceland', "\u{1F1EE}\u{1F1F8}"],
        '355' => ['AL', 'Albania', "\u{1F1E6}\u{1F1F1}"],
        '356' => ['MT', 'Malta', "\u{1F1F2}\u{1F1F9}"],
        '357' => ['CY', 'Cyprus', "\u{1F1E8}\u{1F1FE}"],
        '358' => ['FI', 'Finland', "\u{1F1EB}\u{1F1EE}"],
        '359' => ['BG', 'Bulgaria', "\u{1F1E7}\u{1F1EC}"],
        '370' => ['LT', 'Lithuania', "\u{1F1F1}\u{1F1F9}"],
        '371' => ['LV', 'Latvia', "\u{1F1F1}\u{1F1FB}"],
        '372' => ['EE', 'Estonia', "\u{1F1EA}\u{1F1EA}"],
        '373' => ['MD', 'Moldova', "\u{1F1F2}\u{1F1E9}"],
        '374' => ['AM', 'Armenia', "\u{1F1E6}\u{1F1F2}"],
        '375' => ['BY', 'Belarus', "\u{1F1E7}\u{1F1FE}"],
        '376' => ['AD', 'Andorra', "\u{1F1E6}\u{1F1E9}"],
        '380' => ['UA', 'Ukraine', "\u{1F1FA}\u{1F1E6}"],
        '381' => ['RS', 'Serbia', "\u{1F1F7}\u{1F1F8}"],
        '382' => ['ME', 'Montenegro', "\u{1F1F2}\u{1F1EA}"],
        '385' => ['HR', 'Croatia', "\u{1F1ED}\u{1F1F7}"],
        '386' => ['SI', 'Slovenia', "\u{1F1F8}\u{1F1EE}"],
        '387' => ['BA', 'Bosnia and Herzegovina', "\u{1F1E7}\u{1F1E6}"],
        '389' => ['MK', 'North Macedonia', "\u{1F1F2}\u{1F1F0}"],
        '420' => ['CZ', 'Czech Republic', "\u{1F1E8}\u{1F1FF}"],
        '421' => ['SK', 'Slovakia', "\u{1F1F8}\u{1F1F0}"],
        '502' => ['GT', 'Guatemala', "\u{1F1EC}\u{1F1F9}"],
        '503' => ['SV', 'El Salvador', "\u{1F1F8}\u{1F1FB}"],
        '504' => ['HN', 'Honduras', "\u{1F1ED}\u{1F1F3}"],
        '505' => ['NI', 'Nicaragua', "\u{1F1F3}\u{1F1EE}"],
        '506' => ['CR', 'Costa Rica', "\u{1F1E8}\u{1F1F7}"],
        '507' => ['PA', 'Panama', "\u{1F1F5}\u{1F1E6}"],
        '509' => ['HT', 'Haiti', "\u{1F1ED}\u{1F1F9}"],
        '590' => ['GP', 'Guadeloupe', "\u{1F1EC}\u{1F1F5}"],
        '591' => ['BO', 'Bolivia', "\u{1F1E7}\u{1F1F4}"],
        '592' => ['GY', 'Guyana', "\u{1F1EC}\u{1F1FE}"],
        '593' => ['EC', 'Ecuador', "\u{1F1EA}\u{1F1E8}"],
        '595' => ['PY', 'Paraguay', "\u{1F1F5}\u{1F1FE}"],
        '596' => ['MQ', 'Martinique', "\u{1F1F2}\u{1F1F6}"],
        '597' => ['SR', 'Suriname', "\u{1F1F8}\u{1F1F7}"],
        '598' => ['UY', 'Uruguay', "\u{1F1FA}\u{1F1FE}"],
        '599' => ['CW', 'Curacao', "\u{1F1E8}\u{1F1FC}"],
        '670' => ['TL', 'East Timor', "\u{1F1F9}\u{1F1F1}"],
        '673' => ['BN', 'Brunei', "\u{1F1E7}\u{1F1F3}"],
        '675' => ['PG', 'Papua New Guinea', "\u{1F1F5}\u{1F1EC}"],
        '676' => ['TO', 'Tonga', "\u{1F1F9}\u{1F1F4}"],
        '677' => ['SB', 'Solomon Islands', "\u{1F1F8}\u{1F1E7}"],
        '679' => ['FJ', 'Fiji', "\u{1F1EB}\u{1F1EF}"],
        '685' => ['WS', 'Samoa', "\u{1F1FC}\u{1F1F8}"],
        '689' => ['PF', 'French Polynesia', "\u{1F1F5}\u{1F1EB}"],
        '852' => ['HK', 'Hong Kong', "\u{1F1ED}\u{1F1F0}"],
        '853' => ['MO', 'Macau', "\u{1F1F2}\u{1F1F4}"],
        '855' => ['KH', 'Cambodia', "\u{1F1F0}\u{1F1ED}"],
        '856' => ['LA', 'Laos', "\u{1F1F1}\u{1F1E6}"],
        '880' => ['BD', 'Bangladesh', "\u{1F1E7}\u{1F1E9}"],
        '886' => ['TW', 'Taiwan', "\u{1F1F9}\u{1F1FC}"],
        '960' => ['MV', 'Maldives', "\u{1F1F2}\u{1F1FB}"],
        '961' => ['LB', 'Lebanon', "\u{1F1F1}\u{1F1E7}"],
        '962' => ['JO', 'Jordan', "\u{1F1EF}\u{1F1F4}"],
        '963' => ['SY', 'Syria', "\u{1F1F8}\u{1F1FE}"],
        '964' => ['IQ', 'Iraq', "\u{1F1EE}\u{1F1F6}"],
        '965' => ['KW', 'Kuwait', "\u{1F1F0}\u{1F1FC}"],
        '966' => ['SA', 'Saudi Arabia', "\u{1F1F8}\u{1F1E6}"],
        '967' => ['YE', 'Yemen', "\u{1F1FE}\u{1F1EA}"],
        '968' => ['OM', 'Oman', "\u{1F1F4}\u{1F1F2}"],
        '970' => ['PS', 'Palestine', "\u{1F1F5}\u{1F1F8}"],
        '971' => ['AE', 'United Arab Emirates', "\u{1F1E6}\u{1F1EA}"],
        '972' => ['IL', 'Israel', "\u{1F1EE}\u{1F1F1}"],
        '973' => ['BH', 'Bahrain', "\u{1F1E7}\u{1F1ED}"],
        '974' => ['QA', 'Qatar', "\u{1F1F6}\u{1F1E6}"],
        '975' => ['BT', 'Bhutan', "\u{1F1E7}\u{1F1F9}"],
        '976' => ['MN', 'Mongolia', "\u{1F1F2}\u{1F1F3}"],
        '977' => ['NP', 'Nepal', "\u{1F1F3}\u{1F1F5}"],
        '992' => ['TJ', 'Tajikistan', "\u{1F1F9}\u{1F1EF}"],
        '993' => ['TM', 'Turkmenistan', "\u{1F1F9}\u{1F1F2}"],
        '994' => ['AZ', 'Azerbaijan', "\u{1F1E6}\u{1F1FF}"],
        '995' => ['GE', 'Georgia', "\u{1F1EC}\u{1F1EA}"],
        '996' => ['KG', 'Kyrgyzstan', "\u{1F1F0}\u{1F1EC}"],
        '998' => ['UZ', 'Uzbekistan', "\u{1F1FA}\u{1F1FF}"],
        '1242' => ['BS', 'Bahamas', "\u{1F1E7}\u{1F1F8}"],
        '1246' => ['BB', 'Barbados', "\u{1F1E7}\u{1F1E7}"],
        '1264' => ['AI', 'Anguilla', "\u{1F1E6}\u{1F1EE}"],
        '1268' => ['AG', 'Antigua and Barbuda', "\u{1F1E6}\u{1F1EC}"],
        '1284' => ['VG', 'British Virgin Islands', "\u{1F1FB}\u{1F1EC}"],
        '1340' => ['VI', 'US Virgin Islands', "\u{1F1FB}\u{1F1EE}"],
        '1345' => ['KY', 'Cayman Islands', "\u{1F1F0}\u{1F1FE}"],
        '1441' => ['BM', 'Bermuda', "\u{1F1E7}\u{1F1F2}"],
        '1473' => ['GD', 'Grenada', "\u{1F1EC}\u{1F1E9}"],
        '1649' => ['TC', 'Turks and Caicos', "\u{1F1F9}\u{1F1E8}"],
        '1664' => ['MS', 'Montserrat', "\u{1F1F2}\u{1F1F8}"],
        '1670' => ['MP', 'Northern Mariana Islands', "\u{1F1F2}\u{1F1F5}"],
        '1671' => ['GU', 'Guam', "\u{1F1EC}\u{1F1FA}"],
        '1684' => ['AS', 'American Samoa', "\u{1F1E6}\u{1F1F8}"],
        '1758' => ['LC', 'Saint Lucia', "\u{1F1F1}\u{1F1E8}"],
        '1767' => ['DM', 'Dominica', "\u{1F1E9}\u{1F1F2}"],
        '1784' => ['VC', 'Saint Vincent', "\u{1F1FB}\u{1F1E8}"],
        '1787' => ['PR', 'Puerto Rico', "\u{1F1F5}\u{1F1F7}"],
        '1809' => ['DO', 'Dominican Republic', "\u{1F1E9}\u{1F1F4}"],
        '1868' => ['TT', 'Trinidad and Tobago', "\u{1F1F9}\u{1F1F9}"],
        '1869' => ['KN', 'Saint Kitts and Nevis', "\u{1F1F0}\u{1F1F3}"],
        '1876' => ['JM', 'Jamaica', "\u{1F1EF}\u{1F1F2}"],
    ];

    /**
     * Detect country name from a phone number string.
     * Returns null if no match found.
     */
    public static function detectCountry(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (empty($digits)) {
            return null;
        }

        // Try longest prefixes first (4 → 3 → 2 → 1 digits)
        for ($len = 4; $len >= 1; $len--) {
            if (strlen($digits) >= $len) {
                $prefix = substr($digits, 0, $len);
                if (isset(self::COUNTRIES[$prefix])) {
                    return self::COUNTRIES[$prefix][1];
                }
            }
        }

        return null;
    }

    /**
     * Check if a phone number has a valid country code prefix.
     */
    public static function hasCountryCode(string $phone): bool
    {
        return self::detectCountry($phone) !== null;
    }

    /**
     * Normalize phone: strip spaces/dashes, ensure + prefix.
     */
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)\.]/', '', $phone);

        $hadPlus = str_starts_with($phone, '+');
        if (! $hadPlus) {
            $phone = '+' . $phone;
        }

        // If user entered exactly 10 digits (no country code), assume MX mobile
        // and prepend +521 (most common case for this project).
        if (! $hadPlus && preg_match('/^\+(\d{10})$/', $phone, $m)) {
            return '+521' . $m[1];
        }

        // Mexico: WhatsApp requires the "1" mobile prefix after country code 52.
        // +52 followed by exactly 10 digits -> treat as mobile and insert the 1.
        if (preg_match('/^\+52(\d{10})$/', $phone, $m)) {
            $phone = '+521' . $m[1];
        }

        return $phone;
    }

    /**
     * Get all countries as array for frontend consumption.
     * Returns [{ code, iso2, name, flag }]
     */
    public static function allForFrontend(): array
    {
        $result = [];
        foreach (self::COUNTRIES as $code => $data) {
            $result[] = [
                'code' => $code,
                'iso2' => $data[0],
                'name' => $data[1],
                'flag' => $data[2],
            ];
        }

        // Sort by name
        usort($result, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $result;
    }
}
