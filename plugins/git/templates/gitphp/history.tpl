{*
 *  history.tpl
 *  gitphp: A PHP git repository browser
 *  Component: History view template
 *
 *  Copyright (C) 2009 Christopher Han <xiphux@gmail.com>
 *}
{include file='header.tpl'}

 {* Page header *}
 <div class="page_nav">
   {include file='nav.tpl' treecommit=$commit}
   <br /><br />
 </div>

 {include file='title.tpl' titlecommit=$commit}

 {include file='path.tpl' pathobject=$blob target='blob'}
 
 <table cellspacing="0">
   {* Display each history line *}
   {foreach from=$blob->GetHistory() item=historyitem}
     {assign var=historycommit value=$historyitem->GetCommit()}
     <tr class="{cycle values="light,dark"}">
       <td title="{if $historycommit->GetAge() > 60*60*24*7*2}{$historycommit->GetAge()|agestring|escape}{else}{$historycommit->GetCommitterEpoch()|date_format:"%Y-%m-%d"}{/if}"><em>{if $historycommit->GetAge() > 60*60*24*7*2}{$historycommit->GetCommitterEpoch()|date_format:"%Y-%m-%d"}{else}{$historycommit->GetAge()|agestring}{/if}</em></td>
       <td><em>{$historycommit->GetAuthorName()|escape}</em></td>
       <td><a href="{$SCRIPT_NAME}?a=commit&amp;h={$historycommit->GetHash()}" class="list commitTip" {if strlen($historycommit->GetTitle()) > 50}title="{$historycommit->GetTitle()}"{/if}><strong>{$historycommit->GetTitle(50)|escape:'html'}</strong></a>
       {include file='refbadges.tpl' commit=$historycommit}
       </td>
       <td class="link"><a href="{$SCRIPT_NAME}?a=commit&amp;h={$historycommit->GetHash()}">{t}commit{/t}</a> | <a href="{$SCRIPT_NAME}?a=commitdiff&amp;h={$historycommit->GetHash()|urlencode}">{t}commitdiff{/t}</a> | <a href="{$SCRIPT_NAME}?a=blob&amp;hb={$historycommit->GetHash()}&amp;f={$blob->GetPath()}">{t}blob{/t}</a>{if $blob->GetHash() != $historyitem->GetToHash()} | <a href="{$SCRIPT_NAME}?a=blobdiff&amp;h={$blob->GetHash()|urlencode}&amp;hp={$historyitem->GetToHash()|urlencode}&amp;hb={$historycommit->GetHash()|urlencode}&amp;f={$blob->GetPath()|urlencode}">{t}diff to current{/t}</a>{/if}
       </td>
     </tr>
   {/foreach}
 </table>

 {include file='footer.tpl'}

