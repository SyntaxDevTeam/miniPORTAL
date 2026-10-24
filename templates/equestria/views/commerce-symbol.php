<?php
declare(strict_types=1);
/** @var string $type Closed template symbol identifier. */
    echo '<svg viewBox="0 0 120 120" fill="none" aria-hidden="true" focusable="false">';
    if($type==='rank'){
        echo '<path d="m21 41 23 15 16-28 16 28 23-15-10 48H31Z" fill="currentColor" opacity=".16"/><path d="m21 41 23 15 16-28 16 28 23-15-10 48H31Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M32 78h56M37 96h46M60 58v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="m60 48 5 7-5 7-5-7Z" fill="currentColor"/><circle cx="21" cy="36" r="3" fill="currentColor"/><circle cx="99" cy="36" r="3" fill="currentColor"/><circle cx="60" cy="22" r="3" fill="currentColor"/>';
    }elseif($type==='key'){
        echo '<circle cx="39" cy="39" r="24" fill="currentColor" opacity=".12"/><circle cx="39" cy="39" r="24" stroke="currentColor" stroke-width="2"/><circle cx="39" cy="39" r="9" stroke="currentColor" stroke-width="2"/><path d="m56 56 46 46-8 8-12-12-8 8-10-10 8-8-16-16" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m64 64 27 27" stroke="currentColor" stroke-width="1.5" opacity=".5"/>';
    }elseif($type==='bundle'){
        echo '<path d="m25 46 35-19 35 19v43l-35 19-35-19Z" fill="currentColor" opacity=".1"/><path d="m25 46 35-19 35 19v43l-35 19-35-19Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m25 46 35 19 35-19M60 65v43M43 37l35 19v43" stroke="currentColor" stroke-width="2"/><path d="M60 27c-2-17-27-20-24-6 2 9 24 6 24 6Zm0 0c2-17 27-20 24-6-2 9-24 6-24 6Z" stroke="currentColor" stroke-width="2"/>';
    }else{
        echo '<path d="M35 26h50l20 28-45 53L15 54Z" fill="currentColor" opacity=".14"/><path d="M35 26h50l20 28-45 53L15 54Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m35 26 8 28 17 53 17-53 8-28M15 54h90M43 54l17-28 17 28" stroke="currentColor" stroke-width="1.5" opacity=".8"/>';
    }
    echo '</svg>';
