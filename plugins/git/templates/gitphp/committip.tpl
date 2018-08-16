{*
 *  committip.tpl
 *  gitphp: A PHP git repository browser
 *  Component: Commit tooltip template
 *
 *  Copyright (C) 2010 Christopher Han <xiphux@gmail.com>
 *}
<div>
{t}author{/t}: {$commit->GetAuthor()|escape} ({$commit->GetAuthorEpoch()|date_format:"%Y-%m-%d %H:%M:%S"})
<br />
{t}committer{/t}: {$commit->GetCommitter()|escape} ({$commit->GetCommitterEpoch()|date_format:"%Y-%m-%d %H:%M:%S"})
<br /><br />
{foreach from=$commit->GetComment() item=line}
{if strncasecmp(trim($line),'Signed-off-by:',14) == 0}
<span class="signedOffBy">{$line|escape}</span>
{else}
{$line|escape}
{/if}
<br />
{/foreach}
</div>
