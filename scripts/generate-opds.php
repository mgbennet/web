<?
$longopts = array("webroot:", "weburl:");
$options = getopt("", $longopts);
$webRoot = $options["webroot"] ?? "/standardebooks.org/web";
$webUrl = $options["weburl"] ?? "https://standardebooks.org";

$updatedTimestamp = gmdate('Y-m-d\TH:i:s\Z');

$contentFiles = explode("\n", trim(shell_exec('find ' . escapeshellarg($webRoot . '/www/ebooks/') . ' -name "content.opf" | sort') ?? ''));
$sortedContentFiles = [];

foreach($contentFiles as $path){
	if($path == '')
		continue;

	$xml = new SimpleXMLElement(str_replace('xmlns=', 'ns=', file_get_contents($path) ?: ''));
	$xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');

	$updated = $xml->xpath('/package/metadata/meta[@property="dcterms:modified"]') ?: [];
	$identifier = $xml->xpath('/package/metadata/dc:identifier') ?: [];

	if(sizeof($identifier) > 0 && sizeof($updated) > 0){
		$sortedContentFiles[(string)$updated[0] . ' ' . $identifier[0]] = $xml;
	}
}

krsort($sortedContentFiles);

ob_start();
print("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n");

/* Notes:

- *All* OPDS feeds must contain a rel="crawlable" link pointing to the /opds/all feed

- The <fh:complete/> element is required to note this as a "Complete Acquisition Feeds"; see https://specs.opds.io/opds-1.2#25-complete-acquisition-feeds

*/
?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:schema="http://schema.org/" xmlns:fh="http://purl.org/syndication/history/1.0">
	<id><?= $webUrl ?>/opds/all</id>
	<link href="<?= $webUrl ?>/opds/all" rel="self" type="application/atom+xml;profile=opds-catalog;kind=acquisition"/>
	<link href="<?= $webUrl ?>/opds" rel="start" type="application/atom+xml;profile=opds-catalog;kind=navigation"/>
	<link href="<?= $webUrl ?>/opds/all" rel="crawlable" type="application/atom+xml;profile=opds-catalog;kind=acquisition"/>
	<title>All Standard Ebooks</title>
	<subtitle>Free and liberated ebooks, carefully produced for the true book lover.</subtitle>
	<icon><?= $webUrl ?>/images/logo.png</icon>
	<updated><?= $updatedTimestamp ?></updated>
	<fh:complete/>
	<author>
		<name>Standard Ebooks</name>
		<uri><?= $webUrl ?></uri>
	</author>
	<? foreach($sortedContentFiles as $xml){

	$authors = array();
	$temp = $xml->xpath('/package/metadata/dc:identifier') ?: [];
	$identifier = (string)array_shift($temp);
	$url = preg_replace('/^url:/ius', '', $identifier) ?? '';
	$url = preg_replace('/^https:\/\/standardebooks\.org/ius', $webUrl, $url) ?? '';
	$relativeUrl = preg_replace('/^' . preg_quote($webUrl, '/') . '/ius', '', $url) ?? '';

	$temp = $xml->xpath('/package/metadata/dc:title') ?: [];
	$title = array_shift($temp);

	$temp = $xml->xpath('/package/metadata/meta[@property="se:long-description"]') ?: [];
	$longDescription = array_shift($temp);

	$authors = $xml->xpath('/package/metadata/dc:creator') ?: [];

	$temp = $xml->xpath('/package/metadata/dc:date') ?: [];
	$published = array_shift($temp);

	$temp = $xml->xpath('/package/metadata/dc:language') ?: [];
	$language = array_shift($temp);

	$temp = $xml->xpath('/package/metadata/meta[@property="dcterms:modified"]') ?: [];
	$modified = array_shift($temp);

	$temp = $xml->xpath('/package/metadata/dc:description') ?: [];
	$description = array_shift($temp);

	$subjects = $xml->xpath('/package/metadata/dc:subject') ?: [];

	$sources = $xml->xpath('/package/metadata/dc:source') ?: [];

	$filesystemPath = preg_replace('/\/src\/epub\/content.opf$/ius', '', $path) ?? '';
	$temp = glob($filesystemPath . '/dist/*.epub');
	$filename = preg_replace('/^url:https:\/\/standardebooks\.org\/ebooks\//ius', '', $identifier);
	$epubFilename = str_replace('/', '_', $filename) . '.epub';
	$kindleFilename = str_replace('/', '_', $filename) . '.azw3';

	?>
	<entry>
		<id><?= $url ?></id>
		<title><?= $title ?></title>
		<? foreach($authors as $author){
			$id = '';
			if($author->attributes() !== null){
				$id = $author->attributes()->id;
			}
			$temp = $xml->xpath('/package/metadata/meta[@property="se:url.encyclopedia.wikipedia"][@refines="#' . $id . '"]') ?: [];
			$wikiUrl = array_shift($temp);
			$temp = $xml->xpath('/package/metadata/meta[@property="se:name.person.full-name"][@refines="#' . $id . '"]') ?: [];
			$fullName = array_shift($temp);
			$temp = $xml->xpath('/package/metadata/meta[@property="se:url.authority.nacoaf"][@refines="#' . $id . '"]') ?: [];
			$nacoafLink = array_shift($temp);
		?>
		<author>
			<name><?= $author ?></name>
			<? if($wikiUrl !== null){ ?><uri><?= $wikiUrl ?></uri><? } ?>
			<? if($fullName !== null){ ?><schema:alternateName><?= $fullName ?></schema:alternateName><? } ?>
			<? if($nacoafLink !== null){ ?><schema:sameAs><?= $nacoafLink ?></schema:sameAs><? } ?>
		</author>
		<? } ?>
		<published><?= $published ?></published>
		<dc:issued><?= $published ?></dc:issued>
		<updated><?= $modified ?></updated>
		<dc:language><?= $language ?></dc:language>
		<dc:publisher>Standard Ebooks</dc:publisher>
		<? foreach($sources as $source){ ?>
		<dc:source><?= $source ?></dc:source>
		<? } ?>
		<rights>Public domain in the United States; original content released to the public domain via the Creative Commons CC0 1.0 Universal Public Domain Dedication</rights>
		<summary type="text"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></summary>
		<content type="text/html"><?= $longDescription ?></content>
		<? foreach($subjects as $subject){ ?>
		<category scheme="http://purl.org/dc/terms/LCSH" term="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>"/>
		<? } ?>
		<link href="<?= $relativeUrl ?>/dist/cover.jpg" rel="http://opds-spec.org/image" type="image/jpeg"/>
		<link href="<?= $relativeUrl ?>/dist/cover-thumbnail.jpg" rel="http://opds-spec.org/image/thumbnail" type="image/jpeg"/>
		<link href="<?= $relativeUrl ?>/dist/<?= $epubFilename ?>" rel="http://opds-spec.org/acquisition/open-access" type="application/epub+zip" title="Recommended compatible epub"/>
		<link href="<?= $relativeUrl ?>/dist/<?= $epubFilename ?>3" rel="http://opds-spec.org/acquisition/open-access" type="application/epub+zip" title="epub"/>
		<link href="<?= $relativeUrl ?>/dist/<?= preg_replace('/\.epub$/ius', '.kepub.epub', $epubFilename) ?>" rel="http://opds-spec.org/acquisition/open-access" type="application/kepub+zip" title="Kobo Kepub epub"/>
		<link href="<?= $relativeUrl ?>/dist/<?= $kindleFilename ?>" rel="http://opds-spec.org/acquisition/open-access" type="application/x-mobipocket-ebook" title="Amazon Kindle azw3"/>
	</entry>
	<? } ?>
</feed>
<?

// Print the "all feed" to file
$feed = ob_get_contents();
ob_end_clean();

$tempFilename = tempnam('/tmp/', 'se-opds-');

file_put_contents($tempFilename, $feed);
exec('se clean ' . escapeshellarg($tempFilename));

// If the feed has changed compared to the version currently on disk, copy our new version over
// and update the updated timestamp in the master opds index.
try{
	if(filesize($webRoot . '/www/opds/all.xml') !== filesize($tempFilename)){
		$oldFeed = file_get_contents($webRoot . '/www/opds/all.xml');
		$newFeed = file_get_contents($tempFilename);
		if($oldFeed != $newFeed){
			file_put_contents($webRoot . '/www/opds/all.xml', $newFeed);

			// Update the index feed with the last updated timestamp
			$xml = new SimpleXMLElement(str_replace('xmlns=', 'ns=', file_get_contents($webRoot . '/www/opds/index.xml')));
			$xml->registerXPathNamespace('dc', 'http://purl.org/dc/elements/1.1/');
			$xml->registerXPathNamespace('schema', 'http://schema.org/');

			$allUpdated = $xml->xpath('/feed/entry[id="https://standardebooks.org/opds/all"]/updated')[0];
			$allUpdated[0] = $updatedTimestamp;
			file_put_contents($webRoot . '/www/opds/index.xml', str_replace(" ns=", " xmlns=", $xml->asXml()));
			exec('se clean ' . escapeshellarg($webRoot) . '/www/opds/index.xml');
		}
	}
}
catch(Exception $ex){
	rename($tempFilename, $webRoot . '/www/opds/all.xml');
}

unlink($tempFilename);

?>
