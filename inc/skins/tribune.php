<!DOCTYPE HTML>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?php echo $swParsedName ?></title>
<link rel='stylesheet' href="inc/skins/tribune.css"/>
<style><?php echo $swParsedCSS ?></style>
</head>
<body>
<div id='menu1'><p class='menu'><?php echo swSystemMessage("SkinHeader",$lang, true); ?>
<br/> <?php foreach($swLangMenus as $item) {echo $item." " ; } ?>
<br/><br/>
<?php 
echo $swHomeMenu. "<br/>"; 
echo swSystemMessage("SkinMenu",$lang, true). "<br/><br/>";
foreach($swEditMenus as $item) {echo $item."<br/>"; }
echo "<br/>";
foreach($swLoginMenus as $item) {echo $item."<br/>" ; }
echo "</p>
";
echo $swSearchMenu;
echo "</div>";
// echo join(" ",$wiki->internalLinks);

?>

<div id='contenu'>

<div id='menu'>
<?php if ($swError) echo "<div id='error'>$swError</div>"; ?>
</div>


<div id='content'>

<h1><?php echo "$swParsedName" ?></h1>

<div id='parsedContent'><?php echo "

$swParsedContent
" ?>

</div>


<div id="info"><?php echo "$swFooter" ?>
<p class='menu'>
 <?php echo swSystemMessage("Copyright",$lang);  echo swSystemMessage("SkinFooter",$lang, true); ?>
</p>
</div>
</div>
</div>  
</body>
</html>