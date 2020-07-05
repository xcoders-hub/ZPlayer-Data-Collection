<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/Shared/Logger.php';
// require_once __DIR__.'/Config/Config.php';

use Details\Information;
use Sources\WatchSeries\Search;
use Sources\WatchSeries\Servers\Vidcloud;
use Sources\WatchSeries\Shared;
use Sources\WatchSeries\Watch;
use Sources\WatchSeries\Update;

use Shared\Config;

$secret = 'c$HCh_^s@#QbsKkVy-CeQgV@bcvLwEg#!CCVS4rWkat2r*9Q3V!$Cs#@s+J5f%+Hv_ts?%#G6mJybga2Y_ben3@+-#N_&j8JMG%vDC^Z35$9h!4MWd#%bc3Q$6gQMx^SdK*yjtN54X2*R-ZJbT!!&r&mHf?_6_*p$4-sYvHkDa_XDPh+CH%FQRH&nb&5yLEmGn%?XFpJJ#Nbx5Sw2S39FUkwhtwHs%+x_-$Q6CApDJ8ZSb2_%k9s98QW#LSCjWdpcLBvWh4vc43RZ%7nUC=LY=SES_KDhTuQ8M=c5Bs2^d2^KrjvXR$*DhhpBz#tJAgNx@5?T2acD6=cw*JPs^jx-$P-v6x?Y+gJShd9@ES5^Z_S?5kyFWUzf47B!W4vK6z^ZR8MH$96@zsXa%czPKMY67zkX&paaYt#ukxR6Zxq9BHxp4qk+F_7Eq$fR&nt#66-UKPkyEzD#&WN*KU8b5Z85FyS_Z?_A%Ppx6g7+BZP&72_gC4&LRRP?yVb^h&Be48#3&L?K!3BH$sPhEQ8h=w2N5fk_WkvfzrqnhY=U$EA7bEkmaAPRqpX-jP5cdP%ZWn^b^8GUJafyJrumALSHTYG=YDdp9@LBnR@c!@uLcvXJC2?xNSc7x-x3C8-ZRWTktg_tybS+=@98ywAH&jLM_A!2s+Nx85v?-8!!H_Hth%fmGzab7ez2sg4=NdHND4*cBpqmQLfMzu@P&CTKEuEJsgGm3SAAg7Hp&&reRnQu=EPjp@KDubfwMwz$r%QJNx2hts%ECTJL4rvzPvZgNPna-CJxCPww4L=5VtJqLBa+hK?rjss@8Rp_W?=Xux6%@MfpPdvLzu5Rh_B+KLwq@uqG5YXG?b9?&Z9LS+6G=sfcqBM&9!2ATn=N9y9-su3fJg_APn=7+xEpqQWNhuyutNUmEQ8K3#7jy8$=YA!z=Bx-#HQ7qWET9?S25Uw-Uen@tCc5P#$TUbYVkb7K#8$^XX?uMcgg-@RsGX^6Y32mY+3%z52yMwS5RgLM3P=FFc_7&j*%XEU$Yv7ER_*H3^rT=hr&vaZpURssSFZYFuwbhQf*VqHdEPKDRTLr6pHVV=T$nMa+UyBVRTeZ+q!DAG4JdK35*_&29qKcPvSR_Qcfk6ePz&Kyvm+cVY?aCrQN#6Hkhpst@rQem^P8#$3NT+Hjy+T6GBPRn@kQMyH6fcFM9#Tk4kRxx!4#^494mmLEa_qCKk=5MEwseWZM^%9cuPURSS$2qfy4NzMH-ccZ4=a4N-2uLxRQ&&9fXEdpFM@K=c#xG_!d*BEUpDfRJj2!NFT$AwXD32f$&F9u=WFzcLExDgdRMUt$K_d-KMq=wfT*%_-2V349dnNKZGuz&ts5sqxPy8BvK3FrmzSf%%@6yEXvW*kPw3KVK5pG!+RwqHbgy*rUjmGL+r3jGXxgZ7xkbRCc#Xu$rg@9wa3JtKnW8h3gmVFGj9ARtEW2-&CdR728J2JfkvgN8gjY!jxwXwMxbgJsNBKYTWHBJNX%bZS?%yZ7umYhp-VS_yVD3W5jDCXXuR_b%K*M&L=yVnTcQG^G!R=+#nKgrPa9KDYD-94VBc8X^BM^vVZx_fgbyfdTBE$AEj#*UP4-T22%Z3T3tsgxtqXZ8j4Y-uNKV3^C?$mKg2Ud!NKAS%#zq%%!P7vNaq2wfcPPe=DnR^CE^!b54ts!c-vtQJ_+#458MKsr89xwRYn#ZasS_msANsmzKD8F3bFBKsW#-@J*!Zxe%KcPMM%*Ae#M-6bt__nQye4LWhNLZtBG2kXtx?Jkg+sqsS^2*MwD@$G$%Z%5$?75hEsSy8FCrG7Cv29$5sLC?5MgeXme&zg?8UFL5^2Ypjuq%L!hgKGtB6=T%Us8+wXcn-mE6MuWSVed=b^RD2MLAekj$XH^@NDUunVsE+bCEDacR4h9%ru7qrKm?DANbt2^VV+*#x%S%#*^LzWSaN_6MfvA4Tvp7H$YJ4_UVf#rtTa99=w^XXwEKFH#ekmMn!Ady?u$_xkKS4*XEUjM!tMyba@?@=!Zek@qX%-Cef9-nf^*awzY-fGBZSksd$8k8tV';

$conf = new Config($logger);
$Config = $conf->load_config($secret);

$title = 'mandalorion';
$released_year = 2019;
$content_type = 'series';

$content = new Information($Config,$logger);

$logger->debug("---------------  Start: $title ---------------");

$details = $content->overview($title,$content_type,$released_year);

$details->url  = null;

$content->send_details($details,$content_type);
// print_r($details);

// print_r($details);

$logger->debug("---------------  Complete: $title ---------------");



// $search = new Search($Config,$logger);

// $shared =  new Shared($Config,$logger);
// $search->parent_page_search('star wars new hope');

// $results = $search->search_results($title);

// die(print_r( $results ));

// die(print_r( $shared->parse_results($results,$content_type,$released_year) ));


// $watch = new Watch($Config,$logger);
// $sources = $watch->fetch_sources('https://vidcloud9.com/streaming.php?id=MTk4OTQ=',true);
// print_r($sources);

// $watch = new Vidcloud($Config,$logger);
// $url = 'https://vidcloud9.com/streaming.php?id=ODMxOQ==&title=Star+Wars%3A+Episode+Iv+-+A+New+Hope+HD+720p+&typesub=SUB&sub=L3N0YXItd2Fycy1lcGlzb2RlLWl2LWEtbmV3LWhvcGUtaGQtNzIwcC9zdGFyLXdhcnMtZXBpc29kZS1pdi1hLW5ldy1ob3BlLWhkLTcyMHAudnR0&cover=L3N0YXItd2Fycy1lcGlzb2RlLWl2LWEtbmV3LWhvcGUtZWdzL2NvdmVyLnBuZw==';
// preg_match('/\.php\?(.+)/',$url,$matches);
// $video_id = $matches[1];
// $sources = $watch->video_locations($video_id);
// print_r($sources);

//

// ./vendor/bin/phpunit --colors --testdox tests

// $update = new Update($Config,$logger);
// $update->content_set_parent_url();


//0M(`F0O~@@J_=S$a1[zpYn<fnr$5,HU`y^@EWVENS@7Laj@6*<4%AWD-?VFgU/l
?>