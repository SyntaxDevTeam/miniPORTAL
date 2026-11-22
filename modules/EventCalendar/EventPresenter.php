<?php

declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Modules\EventCalendar;
use DateTimeImmutable;
use DateTimeZone;

final class EventPresenter
{
    public static function card(array $e, string $now): array
    {
        $zone=new DateTimeZone('Europe/Warsaw');$utc=new DateTimeZone('UTC');
        $start=(new DateTimeImmutable($e['starts_at'],$utc))->setTimezone($zone);$end=(new DateTimeImmutable($e['ends_at'],$utc))->setTimezone($zone);
        $phase=$e['ends_at']<=$now?'past':($e['starts_at']<=$now?'live':'upcoming');
        return $e+['title'=>$e['public_title'],'phase'=>$phase,'phase_label'=>['past'=>'Zakończone','live'=>'Właśnie trwa','upcoming'=>'Nadchodzące'][$phase],
            'start_label'=>$start->format('d.m.Y · H:i'),'end_label'=>$end->format('d.m.Y · H:i'),
            'start_iso'=>$start->format(DATE_ATOM),'end_iso'=>$end->format(DATE_ATOM),
            'day'=>$start->format('d'),'month'=>['sty','lut','mar','kwi','maj','cze','lip','sie','wrz','paź','lis','gru'][(int)$start->format('n')-1],
            'range'=>$start->format('d.m.Y H:i').' – '.$end->format('d.m.Y H:i'),'url'=>'/events/view?id='.$e['id']];
    }
    public static function month(string $value): array
    {
        $zone=new DateTimeZone('Europe/Warsaw');$date=DateTimeImmutable::createFromFormat('!Y-m',$value,$zone);
        if(!$date || $date->format('Y-m')!==$value || $date->format('Y')<'2000' || $date->format('Y')>'2100') $date=new DateTimeImmutable('first day of this month midnight',$zone);
        $end=$date->modify('+1 month');
        return ['date'=>$date,'from'=>$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'to'=>$end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'month'=>['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec','Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'][(int)$date->format('n')-1].' '.$date->format('Y'),
            'previous'=>'/events?view=month&month='.$date->modify('-1 month')->format('Y-m'),'next'=>'/events?view=month&month='.$end->format('Y-m')];
    }
    public static function ics(array $event): string
    {
        $escape=static fn(string $s): string=>str_replace(["\\","\r","\n",';',','],["\\\\",'',"\\n","\\;","\\,"],$s);
        $description=$event['description'];
        if(($event['content_format']??'plain')==='markdown'){
            $html=(new \SyntaxDevTeam\Cms\Core\ContentRenderer())->render($description,'markdown');
            $description=trim(html_entity_decode(strip_tags(preg_replace('~</(?:p|h[1-6]|li|blockquote|pre|tr)>|<br\s*/?>~i',"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        }
        $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//miniPORTAL//Event Calendar//PL','CALSCALE:GREGORIAN','BEGIN:VEVENT',
            'UID:event-'.$event['id'].'@miniportal','DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'DTSTART:'.gmdate('Ymd\THis\Z',strtotime($event['starts_at'].' UTC')),'DTEND:'.gmdate('Ymd\THis\Z',strtotime($event['ends_at'].' UTC')),
            'SUMMARY:'.$escape($event['public_title']),'DESCRIPTION:'.$escape($description),'LOCATION:'.$escape($event['location']),'END:VEVENT','END:VCALENDAR'];
        // Fold UTF-8 without splitting code points (75 octets, including continuation space).
        $out=[];foreach($lines as $line){$first=true;while(strlen($line)>($first?75:74)){$part=mb_strcut($line,0,$first?75:74,'UTF-8');$out[]=($first?'':' ').$part;$line=substr($line,strlen($part));$first=false;}$out[]=($first?'':' ').$line;}
        return implode("\r\n",$out)."\r\n";
    }
}
