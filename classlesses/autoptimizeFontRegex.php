<?php
// regex to find fonts, externalised to avoid nasty errors for php<5.3

$fonturl_regex = <<<'LOD'
~(?(DEFINE)(?<quoted_content>(["']) (?>[^"'\\]++ | \\{2} | \\. | (?!\g{-1})["'] )*+ \g{-1})(?<comment> /\* .*? \*/ ) (?<url_skip>(?: data: ) [^"'\s)}]*+ ) (?<other_content>(?> [^u}/"']++ | \g<quoted_content> | \g<comment> | \Bu | u(?!rl\s*+\() | /(?!\*) | \g<url_start> \g<url_skip> ["']?+ )++ ) (?<anchor> \G(?<!^) ["']?+ | @font-face \s*+ { ) (?<url_start> url\( \s*+ ["']?+ ) ) \g<comment> (*SKIP)(*FAIL) | \g<anchor> \g<other_content>?+ \g<url_start> \K ((?:(?:https?:)?(?://[[:alnum:]\-\.]+)(?::[0-9]+)?)?\/[^"'\s)}]*+) ~xs
LOD;
?>