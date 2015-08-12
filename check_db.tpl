<div class="titrePage">
  <h2>DB Integrity check</h2>
</div>


<form method="post" action="">

<table class="table2">
	<tr class="throw">
		<td>{'Test'|@translate}</td>
		<td>{'Result'|@translate}</td>
	</tr>
{foreach from=$reference_tests item=reference_test}
	<tr>
		<td>
		<input type="checkbox" name="{$reference_test.ID}" {$reference_test.CHECKED}>
		{$reference_test.LABEL} ({$reference_test.COUNT} references)
		</td>
		<td>
		{if isset($reference_test.result)}
		{if $reference_test.result>0}
			<span style="color:white;background-color:red">{$reference_test.result} FAILED</span>
		{else}
			{'Passed'|@translate}
		{/if}
		{/if}
		</td>
	</tr>
{if isset($reference_test.errors)}
	<tr><td colspan="2">
{foreach from=$reference_test.errors item=error}
		{$error}<br/>
{/foreach}
	</td></tr>
{/if}
{/foreach}
</table>

<p>
  <input type="submit" class="submit" value="{'Submit'|@translate}" name="submit" />
</p>

</form>