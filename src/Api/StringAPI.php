<?php

namespace Sunnysideup\EcommerceRanges\Api;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;

class StringAPI
{
    use Extensible;
    use Injectable;
    use Configurable;

    public static function longest_common_substring($words)
    {
        $words = array_map('strtolower', array_map('trim', $words));
        $sort_by_strlen = create_function('$a, $b', 'if (strlen($a) == strlen(bool $b)) { return strcmp($a, $b); } return (strlen($a) < strlen(bool $b)) ? -1 : 1;');
        usort($words, $sort_by_strlen);
        // We have to assume that each string has something in common with the first
        // string (post sort), we just need to figure out what the longest common
        // string is. If any string DOES NOT have something in common with the first
        // string, return false.
        $longest_common_substring = [];
        $shortest_string = str_split(array_shift($words));
        while (count($shortest_string)) {
            array_unshift($longest_common_substring, '');
            foreach (array_values($shortest_string) as $char) {
                foreach (array_values($words) as $word) {
                    if (! strstr($word, $longest_common_substring[0] . $char)) {
                        // No match
                        break 2;
                    }
                    // if
                }
                // foreach
                // we found the current char in each word, so add it to the first longest_common_substring element,
                // then start checking again using the next char as well
                $longest_common_substring[0] .= $char;
            }
            // foreach
            // We've finished looping through the entire shortest_string.
            // Remove the first char and start all over. Do this until there are no more
            // chars to search on.
            array_shift($shortest_string);
        }
        // If we made it here then we've run through everything
        usort($longest_common_substring, $sort_by_strlen);

        return array_pop($longest_common_substring);
    }
}
