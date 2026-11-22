<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\DiscordIntegration;

/** Secrets remain in config/modules, never in models, audit records or queue payloads. */
final class DiscordConfig
{
    public function __construct(public readonly string $file) {}
    public function read(): array
    {
        if (!is_file($this->file)) return ['revision'=>0,'token'=>'','guild'=>'','routes'=>[]];
        $raw=file_get_contents($this->file);
        if ($raw===false) throw new \RuntimeException('Nie można odczytać konfiguracji Discord.');
        return json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    }
    public static function id(string $id): bool { return preg_match('/\A[1-9][0-9]{16,19}\z/D',$id)===1; }
    public static function webhook(string $url): bool { return preg_match('~\Ahttps://(?:discord\.com|discordapp\.com)/api/webhooks/[0-9]{17,20}/[A-Za-z0-9_-]{40,200}\z~D',$url)===1; }
    public function save(array $input, array $catalog): void
    {
        $dir=dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new \RuntimeException('Brak katalogu konfiguracji.');
        $lock=fopen($this->file.'.lock','c');
        if (!$lock) throw new \RuntimeException('Brak blokady konfiguracji.');
        chmod($this->file.'.lock',0600);
        try {
            if (!flock($lock,LOCK_EX)) throw new \RuntimeException('Brak blokady konfiguracji.');
            $old=$this->read();
            if ((int)($input['revision']??-1)!==(int)$old['revision']) throw new \InvalidArgumentException('Konfiguracja zmieniła się. Odśwież stronę.');
            $token=trim((string)($input['token']??''));
            if ($token==='') $token=$old['token'];
            if (!empty($input['clear_token'])) $token='';
            if ($token!=='' && preg_match('/\A[A-Za-z0-9_.-]{30,250}\z/D',$token)!==1) throw new \InvalidArgumentException('Nieprawidłowy token bota.');
            $guild=trim((string)($input['guild']??$old['guild']));
            if ($guild!=='' && !self::id($guild)) throw new \InvalidArgumentException('Nieprawidłowe ID serwera Discord.');
            $routes=$old['routes'];
            foreach ($catalog as $key=>$label) {
                if (!isset($input['routes'][$key]['mode'])) continue;
                $r=$input['routes'][$key];$mode=$r['mode'];
                if (!in_array($mode,['legacy','disabled','bot','webhook'],true)) throw new \InvalidArgumentException('Nieznany sposób wysyłki.');
                if ($mode==='legacy') {unset($routes[$key]);continue;}
                $channel=trim((string)($r['channel']??''));$url=trim((string)($r['webhook']??''));
                if ($url==='') $url=$routes[$key]['webhook']??'';
                if (!empty($r['clear'])) $url='';
                if ($mode==='bot' && ($token==='' || !self::id($guild) || !self::id($channel))) throw new \InvalidArgumentException('Wysyłka botem wymaga tokenu, serwera i kanału.');
                if ($url!=='' && !self::webhook($url)) throw new \InvalidArgumentException('Nieprawidłowy webhook Discord.');
                if ($mode==='webhook' && $url==='') throw new \InvalidArgumentException('Podaj webhook wybranego zdarzenia.');
                $oldRoute = $routes[$key] ?? [];
                $routes[$key]=['mode'=>$mode,'channel'=>$channel,'webhook'=>$url];
                foreach (['roles' => 'ról', 'users' => 'użytkowników'] as $type => $label) {
                    $ids = isset($r[$type]) ? self::mentionIds($r[$type], $label) : ($oldRoute[$type] ?? []);
                    if ($ids !== []) $routes[$key][$type] = $ids;
                }
            }
            if (($token === '' || $guild === '') && in_array('bot', array_column($routes, 'mode'), true)) {
                throw new \InvalidArgumentException('Przed usunięciem tokenu lub serwera zmień trasy korzystające ze wspólnego bota.');
            }
            // Public notices cannot share a destination with staff-only calendar content.
            $pub=$routes['event_calendar.public']??[];$staff=$routes['event_calendar.staff']??[];
            if (in_array($pub['mode']??'',['bot','webhook'],true) && ($pub['mode']??'')===($staff['mode']??'') && ($pub['mode']==='bot' ? $pub['channel']===$staff['channel'] : $pub['webhook']===$staff['webhook'])) throw new \InvalidArgumentException('Publiczne ogłoszenia i zadania administracji wymagają odrębnych kanałów.');
            $data=['revision'=>$old['revision']+1,'token'=>$token,'guild'=>$guild,'routes'=>$routes];
            $tmp=tempnam($dir,'.discord-');if ($tmp===false) throw new \RuntimeException('Nie można zapisać konfiguracji.');
            try {if (!chmod($tmp,0600) || file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false || !rename($tmp,$this->file)) throw new \RuntimeException('Nie można zapisać konfiguracji.');}
            finally {if (is_file($tmp)) unlink($tmp);}
        } finally {flock($lock,LOCK_UN);fclose($lock);}
    }

    /** @return list<string> */
    private static function mentionIds(string $input, string $label): array
    {
        $ids = array_values(array_unique(preg_split('/[\s,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY)));
        if (count($ids) > 10 || count(array_filter($ids, self::id(...))) !== count($ids)) {
            throw new \InvalidArgumentException('Podaj do 10 poprawnych ID '.$label.' (17–20 cyfr każde).');
        }
        return $ids;
    }
}
