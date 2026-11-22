<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SyntaxDevTeam\Cms\Core\Request;

final class EventInput
{
    public const KINDS = ['event'=>'Wydarzenie dla graczy','task'=>'Zadanie administracji','plan'=>'Plan na przyszłość'];
    public const SECTIONS = [
        'basics'=>['title','state','priority','owner_id','category','starts_at','ends_at','content_format','discord_mode'],
        'plan'=>['plan','resources','risks'],
        'announcement'=>['public_title','summary','description','location','rules','rewards'],
    ];
    public static function editorText(Request $r, string $key, int $max): string
    {
        $value=self::text($r->postString($key),$max);
        $format=$r->postString($key.'_format','markdown');
        if (!in_array($format,['markdown','html'],true)) throw new InvalidArgumentException('Nieprawidłowy format edytora.');
        return $format==='html' ? (new \SyntaxDevTeam\Cms\Core\RichTextSanitizer())->sanitize($value) : $value;
    }
    public const STATES = ['idea'=>'Pomysł','planning'=>'Planowanie','preparing'=>'Przygotowanie','ready'=>'Gotowe','cancelled'=>'Anulowane','archived'=>'Archiwum'];
    public const TASKS = ['todo'=>'Do zrobienia','doing'=>'W toku','blocked'=>'Zablokowane','done'=>'Gotowe'];
    public static function text(string $value, int $max, bool $required=false): string
    {
        $value=trim(str_replace("\r\n", "\n", $value));
        if (($required && $value==='') || mb_strlen($value)>$max || str_contains($value,"\0")) throw new InvalidArgumentException('Uzupełnij wymagane pola i przestrzegaj limitów długości.');
        return $value;
    }
    public static function date(string $value, bool $required=true): ?string
    {
        if ($value==='' && !$required) return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone('Europe/Warsaw'));
        if (!$date || $date->format('Y-m-d\TH:i')!==$value || $date->format('Y')<'2000' || $date->format('Y')>'2100') throw new InvalidArgumentException('Podaj prawidłową datę i godzinę (Europe/Warsaw).');
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    public static function local(?string $utc): string
    {
        return $utc ? (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Warsaw'))->format('Y-m-d\TH:i') : '';
    }
    public static function event(Request $r, ?array $base=null): array
    {
        $section=$r->postString('section');
        if ($section!=='') {
            if (!isset(self::SECTIONS[$section])) throw new InvalidArgumentException('Nieznana sekcja wydarzenia.');
            $values=$base??['state'=>'idea','priority'=>'normal','content_format'=>'markdown','discord_mode'=>'now','category'=>'Wydarzenie'];
            foreach (['starts_at','ends_at'] as $key) $values[$key]=self::local($base[$key]??null);
            foreach (self::SECTIONS[$section] as $key) $values[$key]=$r->postString($key,(string)($values[$key]??''));
            foreach (['plan','resources','risks','description','rules','rewards'] as $key) {
                if (in_array($key,self::SECTIONS[$section],true) && ($values['content_format']??'plain')==='markdown') $values[$key]=self::editorText($r,$key,40000);
            }
            $r=Request::fromArrays([],$values,['REQUEST_METHOD'=>'POST']);
        }
        $data=['entry_kind'=>$base['entry_kind']??$r->postString('entry_kind','event')];
        if (!isset(self::KINDS[$data['entry_kind']])) throw new InvalidArgumentException('Wybierz wydarzenie, zadanie lub plan.');
        foreach (['title'=>180,'public_title'=>180,'summary'=>600,'description'=>30000,'rules'=>12000,'rewards'=>6000,'location'=>180,'category'=>80,'plan'=>40000,'resources'=>16000,'risks'=>16000] as $key=>$max) $data[$key]=self::text($r->postString($key),$max,$key==='title');
        $data['content_format']=$r->postString('content_format','plain');
        if (!in_array($data['content_format'],['plain','markdown'],true)) throw new InvalidArgumentException('Wybierz zwykły tekst lub Markdown.');
        $data['discord_mode']=$r->postString('discord_mode','now');
        if (!in_array($data['discord_mode'],['now','later'],true)) throw new InvalidArgumentException('Wybierz termin publikacji na Discordzie.');
        $data['state']=$r->postString('state');
        if (!isset(self::STATES[$data['state']])) throw new InvalidArgumentException('Wybierz status przygotowania.');
        $data['starts_at']=self::date($r->postString('starts_at'),false);
        $data['ends_at']=self::date($r->postString('ends_at'),false);
        if (($data['starts_at']===null)!==($data['ends_at']===null) || ($data['starts_at']!==null && $data['ends_at']<=$data['starts_at'])) throw new InvalidArgumentException('Koniec musi być późniejszy niż początek. Podaj obie daty lub pozostaw obie puste dla pomysłu.');
        $data['priority']=$r->postString('priority');
        if (!in_array($data['priority'],['low','normal','high','urgent'],true)) throw new InvalidArgumentException('Wybierz priorytet.');
        $data['owner_id']=$r->postInt('owner_id',0)?:null;
        return $data;
    }
    public static function publication(Request $r, array $event): array
    {
        $mode=$r->postString('publication_mode','private');
        if (!in_array($mode,['private','now','scheduled'],true)) throw new InvalidArgumentException('Wybierz sposób publikacji.');
        if (($event['entry_kind']??'event')!=='event' && $mode!=='private') throw new InvalidArgumentException('Zadania i plany nie mogą być publikowane.');
        if ($mode==='private') return ['mode'=>$mode];
        $public=self::publicData($event);
        $at=null;
        if ($mode==='scheduled') {
            $timing=$r->postString('publication_timing','days');
            if ($timing==='days') {
                $days=$r->postInt('publication_days');
                if ($days===null || $days<1 || $days>365) throw new InvalidArgumentException('Podaj od 1 do 365 dni przed wydarzeniem.');
                // Calendar days in the presentation timezone, including DST transitions.
                $at=(new DateTimeImmutable($event['starts_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Warsaw'))->modify('-'.$days.' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } elseif ($timing==='date') $at=self::date($r->postString('publication_date'));
            else throw new InvalidArgumentException('Wybierz liczbę dni albo dokładną datę publikacji.');
            if ($at<=gmdate('Y-m-d H:i:s') || $at>=$event['starts_at']) throw new InvalidArgumentException('Publikacja musi nastąpić w przyszłości i przed rozpoczęciem wydarzenia. Zmień termin albo wybierz publikację teraz.');
        }
        return ['mode'=>$mode,'at'=>$at,'payload'=>json_encode($public,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)];
    }
    public static function publicData(array $event): array
    {
        if (($event['entry_kind']??'event')!=='event') throw new InvalidArgumentException('Zadania i plany są wyłącznie wewnętrzne. Utwórz osobne wydarzenie dla graczy.');
        if (in_array($event['state'],['cancelled','archived'],true) || !$event['starts_at'] || !$event['ends_at'] || $event['public_title']==='' || $event['description']==='') throw new InvalidArgumentException('Publikacja wymaga aktywnego wydarzenia, tytułu publicznego, opisu i terminu od–do.');
        return array_intersect_key($event,array_flip(['public_title','summary','description','rules','rewards','location','category','starts_at','ends_at','content_format']));
    }
}
