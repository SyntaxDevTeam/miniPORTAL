<?php

declare(strict_types=1);

namespace SyntaxDevTeam\Cms\Core;

/** Optional composed profile presentation; the caller supplies display data, never HTML.
 * World collection items may supply image: null|array{url:string,alt:string,note:string}.
 * Images are local public artwork; the association with a horse remains private world data.
 */
interface ProfilePanelThemeInterface
{
    /**
     * @param array{title:string,subtitle:string,status:string,image_url:?string,private:bool,read_only?:bool,
     * metrics:list<array{label:string,value:string,runs?:list<array>}>,statistics:list<array{id?:string,label:string,value:string,note?:string}>,
     * details:list<array{label:string,value:string,timestamp_ms?:int,value_before_time?:bool}>,grids:list<array>,action:?array,
     * rank_renewal?:?array{url:string,label:string},rank_validity_index?:int,rank_validity?:?array{text:string,expires_ms:?int,now_ms:int,prefix?:string},
     * updated_ms?:int,navigation?:list<array{label:string,url:string,active?:bool}>,
     * card?:array{palette:string,layout:string,backdrop?:string,stat_style?:string},show_status?:bool,bio?:string,edit_action?:?array{url:string,label:string},world?:?array,all_statistics?:?array,favorites?:?array} $profile
     */
    public function render_profile_panel(array $profile): void;
}
