<?php
declare(strict_types=1);
namespace SyntaxDevTeam\Cms\Core;

/** Optional composed commerce views. All content is plain display data, never HTML. */
interface CommerceThemeInterface
{
    /** Called before start_page so assets remain conditional. */
    public function prepare_commerce(): void;
    /**
     * @param array{kind:string,title:string,lead:string,notice:string,navigation:list<array>,
     * breadcrumbs?:list<array>,categories?:list<array>,products?:list<array>,
     * product?:array,form?:?array,details?:list<array>,orders?:list<array>,
     * enabled?:bool,account?:?array,active_category?:int,empty?:string} $view
     */
    public function render_commerce(array $view): void;
}
