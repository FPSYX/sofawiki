<?php

if (!defined("SOFAWIKI")) die("invalid acces");



class swRelationLineHandler
{
	var $aggregators = array();
	var $context = array();
	var $currentpath;
	var $errors = array();
	var $functions = array();
	var $globalrelations = array();
	var $globals = array();
	var $interactive;
	var $mode;
	var $mythread;
	var $offsets = array();
	var $programs = array();
	var $result;
	var $stack = array();
	
	function __construct($m = 'HTML')
	{
		$this->mode = $m;
		$this->context = array();
	}
	
	function run($t, $internal = '', $internalbody = '')
	{
		try 
		{
			return $this->run2($t, $internal, $internalbody);
		}
		catch (swExpressionError $err)
		{
			return $this->result.PHP_EOL.'{{error}}'.$err->getMessage();
		}
		catch (swRelationError $err)
		{
			return $this->result.PHP_EOL.'{{error}}'.$err->getMessage();
		}
	}
	
	
	function run2($t, $internal = '', $internalbody = '')
	{
		$lines = array();
		$readlines = array();
		$vlist = array();
		$tlist = array();
		$fields = array();
		$commafields = array();
		$i; $il; $c; $j; $k;
		$locals = array();
		$internals = array();
		$file;
		$r; $r2;
		$command; $line; $mline; $key; $body;
		$found;
		$xp;
		$tx; $ti; $tn;
		$fn;
		$a;
		$firstchart = true;
		$consoleprogress;
		$whilestack = array();
		$ifstack = array();
		$loopcounter;
		$au;
		$tp;
		$ptag; $ptagerror; $ptag2;
		
		$ptag = '';
		$ptagerror = '{{error}}';
		$ptag2 = PHP_EOL;
		if (!$internal) $result = '';
		
		$plines = array();
		$poffset;
		
		if ($internalbody) $internals = explode(',',$internalbody);
		
		$t = str_replace("\r", PHP_EOL, $t);
		$t = str_replace("\n", PHP_EOL, $t);
		
		$lines = explode(PHP_EOL,$t);
		//if ($internal) print_r($lines);
		$c = count($lines);
		
		$rtime = microtime();
		
		$parameteroffset = 0;
		
		for ($i=0; $i < $c; $i++)
		{
			if ($internal) 
			{
				//echo 'internal'. $this->offsets[$internal].' ';;
				
				if ($this->offsets[$internal] >0)
					$il = $i + $this->offsets[$internal]+1-$parameteroffset;
				else
					$il = $i -$this->offsets[$internal]-$parameteroffset;
					
				//echo $il;
			}
			else
			{
				$this->results = '';
				$this->errors = array();
				$il = $i;	
			}
			$ti = $il.''; // to text
			$line = trim($lines[$i]); 
			
			// remove comments
			if (strpos($line,'// ')>-1)
				$line = trim(substr($line,0,strpos($line,'// ')));
			if (! $line or $line == '//') continue;
			// quote
			if (substr($line,0,1)=="'")
			{
				$result .= substr($line,2).$ptag2;
				continue;
			}
			$fields = explode(' ',$line);
			$command = array_shift($fields);
			$body = join(' ',$fields);
			//echo $command;
			switch($command)
			{
				case 'aggregator' : {
										$this->result .= $ptag.$ptagerror.$ti.' Aggregator is not supported'.$ptag2;
										$this->errors[]=$il;
									}
									break;
				case 'assert' : 	if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Assert Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										if (! $r->assert($body))
										{
											$this->result .= $ptag.$ptagerror.$ti.' Error: Assertion error '.$body.$ptag2;
											$this->errors[]=$il;
										}
										$this->stack[]=$r;

									}
									break;  
				case 'beep' : 		{
										$this->result .= $ptag.$ptagerror.$ti.' Beep is not supported'.$ptag2;
										$this->errors[]=$il;
									}
									break; // TO DO 
				case 'chart' : 		{
										$this->result .= $ptag.$ptagerror.$ti.' Chart is not supported'.$ptag2;
										$this->errors[]=$il;
									}
									break; // TO DO 
				case 'compile':		$xp = new swExpression($this->functions);
									$xp->compile($body);
									$this->result .= join(' ',$xp->rpn).$ptag2;
									$xp = null;
									break;
				
				case 'data':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Data Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$found = false;
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										while($i<$c && !$found)
										{
											$i++;
											$il++;
											$ti = $il;
											$line = trim($lines[$i]);
											if ($line != 'end data')
												$r->insert($line);
											else
												$found = true;
										}
										$this->stack[]=$r;
									}
									break;
				case 'deserialize': if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Deserialize Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->deserialize();
										$this->stack[] = $r;
									}
									break;
				case 'difference':	if (count($this->stack)<2)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Difference Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r2 = array_pop($this->stack);
										$r2->difference($r);
										$this->stack[] = $r2;
									}
									break;
				case 'dup':			if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Dup Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$this->stack[] = $r;
										$this->stack[] = $r->doClone();
									}
									break;						
				case 'echo' :		$locals = array();
					
									/*
									if (count($stack)>0)
									{
										$r = $stack[count($stack)-1];
										if (count($r->tuples) > 0)
										{
											$tp = $r->tuples[0];
											$locals = $tp->fields;
										}
									}
									*/
									$xp = new swExpression($this->functions);
									$xp->compile($body);
									$tx = $xp->evaluate($this->globals, $locals);
									$this->result .= $tx.PHP_EOL; // . $ptag2; // ???
									switch (substr($tx,1))
									{
										case '=':
										case '*':
										case '{':
										case '|': break;
										default: $result .= $ptag2 ;// ???
									}
									$xp = null;
									break;
				case 'else':		$loopcounter = 1;
									if (count($ifstack)>0)
									{
										while($i<$c && $loopcounter>0)
										{
											$i++;
											$mline = trim($lines[$i]);
											if (strpos($mline,'// ')>-1)
												$mline = trim(substr($mline,0,strpos($mline,'// ')));
											if (substr($mline,0,2) == 'if')
											{
												$loopcounter++;
											}
											elseif (substr($mline,0,4) == 'else')
											{
												if ($loopcounter == 1) $loopcounter--;
											}
											elseif (substr($mline,0,6) == 'end if')
											{
												$loopcounter--;
											}
										}
									}
									else
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Else is not possible here '.$ptag2;
										$this->errors[]=$il;

									}
									break; 	
				case 'end':			if ($line == 'end while' && count($whilestack) > 0)
										$i = array_pop($whilestack) - 1;
									elseif($line == 'end if')
									{
										if (count($ifstack)>0)
											array_pop($ifstack);
										else
										{
											$this->result .= $ptag.$ptagerror.$ti.' Error : Endif not possible here'.$ptag2;
											$this->errors[]=$il;
										}
									}
									else
									{
										//print_r($lines);echo $line;
										$this->result .= $ptag.$ptagerror.$ti.' Error : End not possible here'.$ptag2;
										$this->errors[]=$il;
									}
									
									break; 	
				case 'extend':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Extend Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										$r->extend($body);
										$this->stack[] = $r;
									}
									break;	
				case 'filter':		$r = swRelationFilter($body);
									$this->stack[] = $r;
									break;				
				case 'format':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Format Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->format1($body);
										$this->stack[] = $r;
									}
									break;				
				case 'formatdump':	if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Formatdump Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$vlist = array_keys($r->formats);
										$tlist = array();
										foreach($vlist as $velem)
										{
											$tlist[] = $velem.' '.$r->formats[$velem];
										}
										$this->result.= $ptag.'Formats: '.join(', ',$tlist).$ptag2;
										$this->stack[] = $r;
									}
									break;	
				case 'function':	$plines = array();
									$plines[] = $line;
									if (array_key_exists(trim($body), $this->offsets))
									{
										$this->result .= $ptag.$ptagerror.$ti.' Warning : Symbol overwritten'.$ptag2;
										$this->errors[]=$il;

									}
									$this->offsets[trim($body)] = $i;
									$found = false;
									while($i<$c && !$found)
									{
										$i++;
										$line = trim($lines[$i]);
										$plines[] = $line;
										if ($line == 'end function')
										$found = true;
									}
									if ($found)
									{
										$fn = new swExpressionCompiledFunction(trim($fields[0]),join(PHP_EOL,$plines),false);
										$this->functions[trim($fields[0])] = $fn;
										
										//print_r($this->functions);
									}
									else
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Function missing end'.$ptag2;
										$this->errors[]=$il;
									}
									break; 
				case 'if':			$xp = new swExpression($this->functions);
									$xp->compile($body);
									$ifstack[] = $i;
									if ($xp->evaluate($this->globals,$locals) != '0')
									{}
									else
									{
										$loopcounter = 1;
										while($i<$c && $loopcounter>0)
										{
											$i++;
											$mline = trim($lines[$i]);
											if (strpos($mline,'// ')>-1)
												$mline = trim(substr($mline,0,strpos($mline,'// ')));
											if (substr($mline,0,2) == 'if')
											{
												$loopcounter++;
											}
											elseif (substr($mline,0,4) == 'else')
											{
												if ($loopcounter == 1) $loopcounter--;
											}
											elseif (substr($mline,0,6) == 'end if')
											{
												$loopcounter--;
											}
										}
									}
									break; 	
				case 'import':		{
										$this->result .= $ptag.$ptagerror.$ti.' Import is not supported'.$ptag2;
										$this->errors[]=$il;
									}
									break;	
				case 'include':		{
										$this->result .= $ptag.$ptagerror.$ti.' Include is not supported'.$ptag2;
										$this->errors[]=$il;
									}
									break;
				case 'insert':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Insert Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										$r->insert($body);
										$this->stack[] = $r;
									}
									break;	
				case 'intersection':	if (count($this->stack)<2)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Intersection Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r2 = array_pop($this->stack);
										$r2->intersection($r);
										$this->stack[] = $r2;
									}
									break;	
				case 'join':	if (count($this->stack)<2)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Join Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										$r2 = array_pop($this->stack);
										$r2->join($r,$body);
										$this->stack[] = $r2;
									}
									break;									
				case 'limit':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Limit Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										$r->limit($body);
										$this->stack[] = $r;
									}
									break;	
				case 'memory':	    break; 	
				case 'order':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Order Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->order($body);
										$this->stack[] = $r;
									}
									break;	
				case 'parameter':	if (count($internals)==0)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Parameter stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$tx = trim(array_shift($internals));
										$xp = new swExpression($this->functions);
										$xp->compile($tx);
										$locals[trim($body)] = $xp->evaluate($this->globals);
										$parameteroffset++;
									}
									break;
									
				case 'pop':			if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Pop Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r = null;
									}
									break;	
				case 'print':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Print Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										
										// nowiki seems not to break on newlines.
										
										switch(trim($body))
										{
											case 'csv':		$this->result .= '<nowiki><textarea style="width:100%;height:200px">'. $r->getCSV($body).'</textarea></nowiki>'; break;
											case 'fields':	$this->result .= $r->toFields($body); break;
											case 'json':	$this->result .= '<nowiki><textarea style="width:100%;height:200px">'. $r->getJSON($body).'</textarea></nowiki>'; break;
											case 'tab':		$this->result .= '<nowiki><textarea style="width:100%;height:200px">'. $r->getTab($body).'</textarea></nowiki>'; break;
											default: 		$this->result .= $r->toHTML($body); break;
										}
										
										
										$this->stack[] = $r;
									}
									break;		
				case 'program':		$plines = array();
									$fields = explode(' ',$body);
									$key = array_shift($fields);
									$body = trim(join(' ',$fields));
									
									if ($body != '')
									{
										$commafields = explode(',',$body);
										$k = count($commafields);
										for($j=0;$j<$k;$j++)
										{
											$plines[] = 'parameter '.trim($commafields[$j]);
										}
										if (array_key_exists($key, $this->offsets))
										{
											$this->result .= $ptag.$ptagerror.$ti.' Warning : Symbol overwritten'.$ptag2;
											$this->errors[]=$il;
										}
										
										$this->offsets[$key] = $i+1;
										$found = false;
										while($i<$c && !$found)
										{
											$i++;
											$line = trim($lines[$i]); 
											if ($line != 'end program')
												$plines[] = $line;
											else
												$found = true;											
										}
										//print_r($plines);
										if ($found)
											$this->programs[$key] = join(PHP_EOL,$plines);
										else
										{
											$this->result .= $ptag.$ptagerror.$ti.' Error : Program missing end'.$ptag2;
											$this->errors[]=$il;
										}										
									}									
									break; 				
				case 'project':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Project Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack); //print_r($r);
										$r->aggregators = $this->aggregators;
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										$r->project($body);
										$this->stack[] = $r;
									}
									break;	
				case 'read':		$r = new swRelation('');
									$enc = 'utf8';
									if (substr(trim($body),0,8)=='encoding')
									{
										$body = trim(substr(trim($body),8));
										$fields = explode(' ',$body);
										$enc = array_shift($fields);
										if (count($fields)==0)
										{
											$this->result .= $ptagerror.$ti.' Error : Missing filename'.$ptag2;
											$this->errors[] = $il;
											$body = '';
										}
										else
											$body = join(' ',$fields);
									}
									$xp = new swExpression($this->functions);
									$xp->compile($body);
									$tn = $xp->evaluate($this->globals,$locals);
									
									if ($tn == '') $tn = ' ';
									if (!$r->validName(str_replace('.csv','',str_replace('.txt','',str_replace('.json','',$tn)))))
									{	
										$this->result .= $ptagerror.$ti.' Error : Invalid filename '.$tn.$ptag2;
										$this->errors[] = $il;
										}
									else
									{
										if ($tn=='')
										{
											$this->result .= $ptagerror.$ti.' Error : Empty filename'.$ptag2;
											$this->errors[] = $il;
										}
										
										elseif(strpos($tn,'.') === FALSE)
										{
											if (array_key_exists($tn, $this->globalrelations))
												$r = $this->globalrelations[$tn];
											else
											{
												$this->result .= $ptagerror.$ti.' Error : Relation does not exist'.$ptag2;
												$this->errors[] = $il;
												$r = new swRelation('');
											}
											$this->stack[] = $r->doClone();
											//print_r($stack);
												
										}
										elseif(true)  // true $currentpath files are in site
										{
											$file1 = 'site/files/'.$tn;
											$file2 = 'site/cache/'.$tn;
											if (!file_exists($file1) and !file_exists($file2))
											{
												$this->result .= $ptagerror.$ti.' Error : File does not exist '.$tn.$ptag2;
												$this->errors[] = $il;
												$tip = '';
											}
											else
											{
												if (file_exists($file1) and file_exists($file2))
												{
													 if (filemtime($file1) >= filemtime($file2)) 
													 	$file = $file1;
													 else
													 	$file = $file2;
												}
												elseif (file_exists($file1)) $file = $file1;
												else $file = $file2;
												
												
												$tip = file_get_contents($file);
											}
											switch($enc)
											{
												case 'macroman': $tip = iconv('macinstosh', 'UTF-8', $tip); break;
												case 'windowslatin1': $tip = mb_convert_encoding($tip, 'UTF-8', 'Windows-1252', $tip); break;
												case 'latin1': $tip = utf8_encode($tip); break;
												case 'utd-8': break;
											}
											if (strlen($tip)>0)
											{
												$r = new swRelation('');
												switch(substr($file,-4))
												{
													case '.csv': 	$r->setCSV(explode(PHP_EOL,$tip));
																	break;
													case '.txt': 	$r->setTab(explode(PHP_EOL,$tip));
																	break;
													case 'json':	$r->setJSON(explode(PHP_EOL,$tip));
																	break;
													default:		$this->result .= $ptagerror.$ti.' Error : Invalid filename '.$tn.$ptag2;
																	$this->errors[] = $il;
																	break;
												}
												$this->stack[] = $r;
											}
											else
											{
												$this->result .= $ptagerror.$ti.' Error : Empty file'.$ptag2;
												$this->errors[] = $il;
											}

										}
									}
									break;

										
										
										
									break;
				case 'relation':	$r = new swRelation($body);
									$this->stack[] = $r;
									break;	
				case 'rename':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Rename Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->rename1($body);
										$this->stack[] = $r;
									}
									break;										
				case 'run':			$fields = explode(' ',$body);
									$key = array_shift($fields);
									$body = join(' ',$fields);
									if (array_key_exists($key, $this->programs))
									{
										$tx = $this->programs[$key];
										$this->result = $this->run2($tx,$key,$body); //!!!
									}
									else
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Program not defined '.$key.$ptag2;
										$this->errors[]=$il;
									}	
									break; 
				case 'select':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Select Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->globals = $this->globals;
										$r->functions = $this->functions;
										$r->locals = $locals;
										$r->select1($body);
										$this->stack[] = $r;
									}
									break;	
				case 'serialize':	if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Serialize Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r->serialize();
										$this->stack[] = $r;
									}
									break;	
				case 'set':			$locals= array();
									if (count($this->stack)>0)
									{
										$r = array_pop($this->stack);
										if (count($r->tuples)>0)
										{
											$tp = array_shift($r->tuples);
											$locals = $tp->fields();
											array_unshift($r->tuples, $tp);
											
										}
										$this->stack[] = $r;
									}
										$fields = explode(' ',$body);
										$key = array_shift($fields);
										$body = join(' ',$fields);
										$xp = new swExpression($this->functions);
										$xp->compile($body);
										$this->globals[$key] = $xp->evaluate($this->globals,$locals);	

									break; 
				case 'stop':		$i=$c; break;
				case 'swap':		if (count($this->stack)<2)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Swap Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r2 = array_pop($this->stack);
										$this->stack[] = $r;
										$this->stack[] = $r2;
									}
									break;	
				case 'template':	if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Template Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$xp = new swExpression($this->functions);
										$xp->compile($body);
										$tn = $xp->evaluate($this->globals, $locals);
										if (!$r->validName($tn))
										{
											$this->result .= $ptag.$ptagerror.$ti.' Error : Invalid name '.$tn.$ptag2;
											$this->errors[]=$il;
										}
										else
										{
											$tmp = swRelationTemplate($tn);
											$this->result .= $r->toTemplate($tmp);											
										}
										$this->stack[] = $r;
									}
									break;
				case 'union':		if (count($this->stack)<2)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$r2 = array_pop($this->stack);
										$r2->union($r);
										$this->stack[] = $r2;
									}
									break;
								
				case 'virtual':		$xp = new swExpression($this->functions);
									$xp->compile($body);
									$te = $xp->evaluate($this->globals,$locals);
									$r = swRelationVirtual($te);
									$this->stack[] = $r;
									break;				
				case 'while':		$xp = new swExpression($this->functions);
									$xp->compile($body);
									if ($xp->evaluate($this->globals) != '0') // no locals
									{
										$whilestack[] = $i;
									}
									else
									{
										$loopcounter = 1;
										while($i<$c && $loopcounter >0)
										{
											$i++;
											$mline = trim($lines[$i]);
											if (strpos($mline,'// ')>-1)
												$mline = trim(substr($mline,0,strpos($mline,'// ')));

											if (substr($mline,0,5)=='while')
												$loopcounter++;
											elseif (substr($mline,0,9)=='end while')
												$loopcounter--;
										}
									}
										
									break; 	
				case 'write':		if (count($this->stack)<1)
									{
										$this->result .= $ptag.$ptagerror.$ti.' Error : Write Stack empty'.$ptag2;
										$this->errors[]=$il;
									}
									else
									{
										$r = array_pop($this->stack);
										$xp = new swExpression($this->functions);
										$xp->compile($body);
										$tn = $xp->evaluate($this->globals, $locals);
										if (!$r->validName(str_replace('.csv','',str_replace('.txt','',str_replace('.json','',$tn)))))
										{	
										$this->result .= $ptagerror.$ti.' Error : Invalid filename '.$tn.$ptag2;
										$this->errors[] = $il;
										}
										else
										{
											$file = 'site/cache/'.$tn;
											$written = 0;
											switch(substr($file,-4))
											{
												case '.csv': 	$written = file_put_contents($file,$r->getCSV()); 
																break;
												case '.txt': 	$written = file_put_contents($file,$r->getTab()); 
																	break;
												case 'json':	$written = file_put_contents($file,$r->getJSON()); 
																	break;
												default:		if (strpos($tn,'.') === FALSE)
																	$this->globalrelations[$tn] = $r->doClone();
																else
																{
																	$this->result .= $ptagerror.$ti.' Error : Invalid filename '.$tn.$ptag2;
																	$this->errors[] = $il;
																}
																	break;
											}
											//print_r($this->globalrelations[$tn]);
											if ($written === FALSE)
											{
												$this->result .= $ptagerror.$ti.' Error : File not written '.$tn.$ptag2;
												$this->errors[] = $il;
											}

										}	
										$this->stack[] = $r;
									}
									break;
				default: 			{
										$this->result .= $ptag.$ptagerror.$ti.' Error : '.$line.$ptag2;
										$this->errors[]=$il;
									}
			}
			
		}   
		return $this->result;
		
	}
	
	function setBeep($notes)
	{
		// stub
	}
	
	function setMessage()
	{
		// stub
	}
		
	
}

class swRelation 
{
	var $header = array();
	var $tuples = array();
	var $globals = array();
	var $locals = array();
	var $formats = array();
	var $functions = array();
	var $aggregators = array();
	
	function __construct($columns)
	{
		if (! is_array($columns))
			$columns = explode(',',$columns);
			
		foreach($columns as $s)
		{
			if ($s != '')
			$this->addColumn($s);
		}
	}
	
	function addColumn($s)
	{
		$s = trim($s);
		if (! $this->validName($s)) throw new swRelationError('Invalid name '.$s,102);
		if (! in_array($s,$this->header)) $this->header[] = $s;
	}

	function arity()
	{
		return count($this->header);
	}
	
	function assert($t)
	{
		$list = explode(' ',$t);
		if (count($list) < 2 ) throw new swRelationError('Assert missing expression',402); 
		$s = array_shift($list);
		return $this->assert2($s,join(' ',$list));
	}
	
	function assert2($method, $expression)
	{
		$xp = new swExpression;
		$d = array();
		$test = array();
		$s; 
		$list = array();
		$i; $c; 
		
		$c = count($this->tuples);
		
		try
		{
		
			switch ($method)
			{
				case 'all': $xp->compile($expression);
							foreach($this->tuples as $tp)
							{
								$d = $tp->fields();
								if ($xp->evaluate($d, $this->globals, $this->locals) == '0')
									return false;
							}
							return true;
				case 'exists': $xp->compile($expression);
							foreach($this->tuples as $tp)
							{
								$d = $tp->fields();
								if ($xp->evaluate($d, $this->globals, $this->locals) == '0')
									return true;
							}
				case 'unique': $xp->compile($expression);
							$test = array();
							foreach($this->tuples as $tp)
							{
								$d = $tp->fields();
								$s = $xp->evaluate($d, $this->globals, $this->locals);
								if (array_key_exists($s, $test))
									return false;
								$test[$s] = true;
							}
							return true;
				case 'columns': $list = explode(',',$expression);
							foreach($list as $s)
							{
								if (! in_array(trim($s), $this->header)) return false;
							}
							return true;
				default: 	throw new swRelationError('Assert invalid method',402);
			}
		}
		catch (Exception $e )
		{
			throw new swRelationError('Assert Runtime Error '.$e->getMessage() ,402);	
		}
		
	}
	
	function cardinality()
	{
		return count($this->tuples);
	}
	
	function cleanColumn($s)
	{
		$result; 
		$i; $c; 
		$ch; 
		$list1 = 'abcdefghijklmnoprsqtuvwxyz';
		$list = 'abcdefghijklmnoprsqtuvwxyz0123456789_';
		
		$c = strlen($s);
		$ch = substr($s,0,1);
		
		if (!stristr($list1,$ch)) $result = '_'; else $result = $ch;
		for($i = 1; $i<$c; $i++)
		{
			$ch = substr($s,$i,1);
			if (!stristr($list,$ch)) $result .= '_'; else $result .= $ch;
		}
		return $result;		
	}
	
	function doClone()
	{
		$result = new swRelation($this->header);
		$result->tuples = array_clone($this->tuples);
		$result->formats = array_clone($this->formats);
		$result->globals = $this->globals;
		$result->functions = $this->functions;
		$result->locals = $this->locals;
		return $result;
		
	}
	
	function delete($condition)
	{
		$xp = new swExpression($this->functions);
		$xp->compile($condition);
		foreach($this->tuples as $k=>$t)
		{
			$d = $t->fields();
			if ( $xp->evaluate($d) != '0' ) unset($this->tuples[$k]);
		}
	}
	
	function deserialize()
	{
		$r;
		$i;$j;$m;$n;
		$list = array();
		$list2 = array();
		$observationkey;
		$tp = array();
		$tp2 = array();
		$d = array();
		$lastobservation;
		$nulltext = '';
		
		if (count($this->header)< 3)
			throw new swRelationError('Deserialize less than 3 properties',602);
		if (count($this->header)> 3)
			throw new swRelationError('Deserialize more than 3 properties',602);
			
		$this->order($this->header(0));
		
		$r = new swRelation('');
		$r->addColumn($this->header(0));
		
		$n = count($this->tuples);
		for ($i = 0; $i<$n; $i++)
		{
			$tp = $this->tuples[$i];
			$r->addColumn($this->cleanColumn($tp->value($this->header[1])));
		}	
		
		$m = count($r->header); //???
		
		$lastobservation = '';
		for ($i=0; $i<$n; $i++)
		{
			$tp = $this->tuples[$i];
			if ($tp->value[$this->header[0]] != 'lastobservation')
			{
				if ($d)
				{
					foreach($r->header as $mf)
					{
						if (!array_key_exists($mf, $d))
							$d[$mf] = $nulltext;
					}
					$tp2 = new swTuple($d);
					$r->tuples[$tp2->hash()] = $tp2;
				}
				$d = array();
				$d[$this->header[0]] = $tp[$this->header[0]];
				$lastobservation = $tp[$this->header[0]];
			}
			$d[$p[$this->header[1]]] = $tp[$this->header[2]];
		}
		if ($d)
		{
			foreach($r->header as $mf)
			{
				if (!array_key_exists($mf, $d))
					$d[$mf] = $nulltext;
			}
			$tp2 = new swTuple($d);
			$r->tuples[$tp2->hash()] = $tp2;
		}
		
		$this->header = $r->header;
		$this->tuples = $r->tuples;
		
	}
	
	function difference($r)
	{
		$e; $tp;
		$i; $c;
		
		$e = $this->emptyTuple();
		$c = count($r->tuples);
		foreach($r->tuples as $tp)
		//for ($i = $c-1; $i>0; $i--)
		{
			//$tp = $r->tuples[$i];
			if ($tp->sameFamily($e))
			{
				if (array_key_exists($tp->hash(), $this->tuples))
					unset($this->tuples[$tp->hash()]);
			}
			else
				throw new swRelationError('Difference different columns',302);
			
		}
		
	}
	
	function emptyTuple()
	{
		$d = array();
		foreach($this->header as $t)
		{
			$d[$t] = '';
		}
		$t = new swTuple($d);
		return $t;
	}
	
	function extend($t)
	{
		$list = explode(' ',$t);
		$first = array_shift($list);
		$rest = join(' ',$list);
		$this->extend2($first, $rest); 
	}
	
	function extend2($label, $expression)
	{
		$newtuples = array();
		
		$this->addColumn($label);
		
		$xp = new swExpression($this->functions);
		$xp->compile($expression);
		
		$c = count($this->tuples);
		$i=0;
		foreach($this->tuples as $tp)
		//for ($i=0;$i<$c;$i++)
		{
			$d = $tp->fields();
			$this->locals['rownumber'] = strval($i+1); $i++;
			$v = $xp->evaluate($d, $this->globals, $this->locals);
			$d[$label] = $v;
			$tp = new swTuple($d);
			$newtuples[$tp->hash()] = $tp;
			//print_r($newtuples);
		}
		
		$this->tuples = $newtuples;
		
	}
	
	function format1($t)
	{
		$pairs = explode(',',$t);
		$this->format12($pairs);
	}
	
	function format12($pairs)
	{
		foreach($pairs as $p)
		{
			$fields = explode(' ',trim($p));
			if (count($fields) != 2)
				throw new swRelationError('Invalid format',121);
			$fields[0] = trim($fields[0]);
			$fields[1] = trim($fields[1]);
			if (!in_array($fields[0], $this->header))
				throw new swRelationError('Unknown format '.$fields[0],122);
			$this->formats[$fields[0]] = $fields[1];
		}
	}
	
	function format2($d, $f)
	{
		if (substr($f,0,1)=='"') $f = substr($f,1);
		if (substr($f,-1,1)=='"') $f = substr($f,0,-1);
		$result = swNumberformat($d,$f);
		//if (abs($d)< 10.0E-12) $result = '';
		if (substr($result,0,1)=="'") $result = substr($result,1);
		return $result;
		
	}
	
	function import($value)
	{
		$lines = explode(PHP_EOL,$value);
		$this->header = array('lines');
		$this->tuples = array();
		foreach($lines as $line)
		{
			$d = array();
			$d['line'] = $line;
			$tp = new swTuple($d);
			$this->tuples[$tp->hash] = $tp;

		}
	}
	
	function insert($t)
	{
		$xp = new swExpression($this->functions);
		$xp->expectedreturn =  count($this->header);
		$xp->compile($t);
		$test = $xp->evaluate($this->globals, $this->locals);
		$pairs = array();
		while (count($xp->stack) >0)
		{
			$pairs[] = array_shift($xp->stack);
		}
		$this->insert2($pairs);
	}
	
	function insert2($fields)
	{
		if (count($fields) != count($this->header))
			throw new swRelationError('Insert Arity',101);
		$d = array();
		$c = count($this->header);
		for($i=0;$i<$c;$i++)
		{
			$d[$this->header[$i]] = $fields[$i];
		}
		$tp = new swTuple($d);
		$this->tuples[$tp->hash()] = $tp;
		
	}
	
	function intersection($r)
	{
		$newtuples = array();
		$e = $this->emptyTuple();
		$c = count($r->tuples);
		foreach($r->tuples as $tp)
		//for($i = $c-1; $i>=0; $i--)
		{
			if ($tp->sameFamily($e))
			{
				if (array_key_exists($tp->hash(), $this->tuples))
					$newtuples[$tp->hash()] = $tp;
		
			}
			else
				throw new swRelationError('Intersection different columns',301);
		}
	}
	
	function join($r,$expression)
	{
		if ($expression == 'cross') { $this->join($r,'1'); return; }
		if ($expression == 'rightsemi') { $r->join($this, 'leftsemi'); $this->header = $r->header; $this->tuples = $r->tuples; return ; }
		if ($expression == 'rightanti') { $r->join($this, 'leftanti'); $this->header = $r->header; $this->tuples = $r->tuples; return ; }
		if ($expression == 'right') { $r->join($this, 'left'); $this->header = $r->header; $this->tuples = $r->tuples; return ; }
		if ($expression == 'outer') { $r2 = $this->doClone(); $r3 = $r->doClone(); $this->join($r,'left'); $r3->join($r2,'left'); $this->union($r3); return; }
		
		if (in_array($expression,array('left','leftsemi','leftanti','natural')))
		{	$this->joinHash($r, $expression); return; }
		
		$commonheader = array();
		$leftheader = array();
		$rightheader = array();
		
		foreach($this->header as $f)
		{
			if (in_array($f,$r->header))
				$commonheader[] = $f;
			else
				$leftheader[] = $f;	
		}
		
		foreach($r->header as $f)
		{
			if (!in_array($f,$this->header))
			{
				$rightheader[] = $f;
				if (array_key_exists($f, $r->formats))
					$this->formats[$f] = $r->formats[$f];
			}	
		}
		
		$header = array_clone($this->header);
		$this->header = array();
		foreach($header as $f)
		{
			if (in_array($f, $commonheader))
				$this->addColumn($f.'_1');
			else
				$this->addColumn($f);
		}
		foreach($r->header as $f)
		{
			if (in_array($f, $commonheader))
				$this->addColumn($f.'_2');
			else
				$this->addColumn($f);
		}
		//print_r($this->header);
		
		$xp = new swExpression;
		$xp->compile($expression);
		
		$c = count($this->tuples);
		$c2 = count($r->tuples);
		
		//print_r($r->tuples);
	
		//print_r($this->header);
		$newtuples = array();
		foreach($this->tuples as $tp1)
		{
			$d10 = $tp1->fields();
			foreach($commonheader as $cf)
			{
				$d10[$cf.'_1'] = $d10[$cf];
				unset($d10[$cf]);
			}
			foreach($leftheader as $cf)
			{
				$d10[$cf] = $d10[$cf];
			}
			$leftfound = false;
			
			// print_r($d10);
			
			
			
			foreach($r->tuples as $tp2)
			{
				$d1 = array_clone($d10);
				$d2 = $tp2->fields();
				foreach($commonheader as $cf)
				{
					$d1[$cf.'_2'] = $d2[$cf];
				}
				foreach($rightheader as $cf)
				{
					$d1[$cf] = $d2[$cf];
				}	
				$tx = $xp->evaluate($d1,$this->globals,$this->locals);
				if ($tx != '0')
				{
					$tp = new swTuple($d1);
					$newtuples[$tp->hash()] = $tp;
				}			
			}
			
		}
		//print_r($this->header);
		/*
		foreach($commonheader as $cf)
		{
			if (!in_array($cf, $this->header))
				array_unshift($this->header,$cf);
		}
		*/
		$this->tuples = $newtuples;
		
		
	}
	
	function joinHash($r, $expression)
	{
		$commonheader = array();
		foreach($this->header as $f)
		{
			if (in_array($f,$r->header))
				$commonheader[] = $f;
			else
				$leftheader[] = $f;	
		}
		if (count($commonheader) == 0)
		{
			$this->header = array();
			$this->tuples = array();
			return;
		}	
		
		foreach($r->header as $f)
		{
			if (!in_array($f,$this->header))
			{
				$rightheader[] = $f;
				if (array_key_exists($f, $r->formats))
					$this->formats[$f] = $r->formats[$f];
			}	
		}
		
		$expression2 = '';
		foreach($commonheader as $f)
		{
			if ($expression2 == '')
				$expression2 = $f.'_1 == '.$f.'_2';
			else
				$expression2 .= ' and '.$f.'_1 == '.$f.'_2';
		}
		
		switch ($expression)
		{
			case 'leftanti' : break;
			case 'natural'  : foreach($rightheader as $f) $this->addColumn($f); break;
			case 'left'  	: foreach($rightheader as $f) $this->addColumn($f); break;
			case 'leftsemi' : break;
			default 		: throw new swRelationError('Join Hash invalid expression ',671);
		}
		
		$dhash = array();
		$tpstack = array();
		
		$c = count($this->tuples);
		$c2 = count($r->tuples);		
		
		foreach($r->tuples as $tp2)
		{
			$d10 = array();
			foreach($commonheader as $cf)
				$d10[$cf] = $tp2->value($cf);
			$tp = new swTuple($d10);
			
			if (array_key_exists($tp->hash(),$dhash))
				$tpstack = $dhash[$tp->hash()];
			else
				$tpstack = array();
			$tpstack[] = $tp2;
			$dhash[$tp->hash()] = $tpstack;
		}
		
		$newtuples = array();
		
		foreach($this->tuples as $tp1)
		{
			$d1 = array();
			foreach($commonheader as $cf)
			{
				$d1[$cf] = $tp1->value($cf);
			}
			$tp = new swTuple($d1);
			if (array_key_exists($tp->hash(),$dhash))
			{
				$tpstack = $dhash[$tp->hash()];
				$tpstack = array_clone($tpstack);
				
				switch ($expression)
				{
					case 'left'		:
					case 'natural'	:   $d10 = $tp1->fields();
										while(count($tpstack)>0)
										{
											$d11 = array_clone($d10);
											$tp2 = array_shift($tpstack);
											$d2 = $tp2->fields();
											foreach($r->header as $t)
												$d11[$t] = $d2[$t];
											$tp11 = new swTuple($d11);
											$newtuples[$tp11->hash()] = $tp11;
										}
										break;
					case 'leftanti' : 	break;
					case 'leftsemi' :	$newtuples[$tp1->hash()] = $tp1;
										break;										
				}			
			}
			else
			{
				switch ($expression)
				{
					case 'left'		:	$d11 = $tp1->fields();
										foreach($r->header as $t)
											$d11[$t] = '';
										$p11 = new swTuple($d11);
										$newtuples[$tp11->hash()] = $tp11;
										break;
					case 'natural'	:   break;
					case 'leftanti' : 	$newtuples[$tp1->hash()] = $tp1;
										break;
					case 'leftsemi' :	break;										
				}
			}
			
		}
		
		$this->tuples = $newtuples;

	}
	
	function limit($t)
	{
		$xp = new swExpression($this->functions);
		$xp->expectedreturn = 2;
		$xp->compile($t);
		$pairs = array();
		$pairs[] = $xp->evaluate($this->globals,$this->locals);
		if (count($xp->stack) > 1)
			$pairs[] = $xp->stack[1];
		if (count($pairs) == 1)
			$this->limit2($pairs[0],count($this->tuples));
		else
			$this->limit2($pairs[0],$pairs[1]);
		
	}
	
	function limit2($start, $length)
	{
		//echo $start.'/'.$length;
		if ($start == 0)
			throw new swRelationError('Limit Start 0',88);
		if ($start > count($this->tuples))
			throw new swRelationError('Limit Start > count',88);
		if ($length < 0)
			throw new swRelationError('Limit Length < 0',88);
			
		$c = count($this->tuples);
		
		if ($length < $c/3 || true)
		{
			$tuples2 = array();
			$i=0;
			foreach($this->tuples as $tp)
			{
				$i++;
				if ($i<$start) continue;
				if ($i>=$start+$length) continue;
				$tuples2[$tp->hash()] = $tp;
			}
			$this->tuples = $tuples2;
			return;
		}
		/*
		for($i=$c-1;$i>=$start+$length-1;$i--)
			unset($this->tuples[$i]);
		for($i=$start-2;$i>=0;$i--)
			unset($this->tuples[$i]);	
		*/	
	}
	
	function order($t)
	{
		$pairs = explode(',',$t);
		$this->order2($pairs);	
	}
	
	
	function order2($pairs)
	{
		$od = new swOrderedDictionary;
		$od->tuples = $this->tuples;
		$od->pairs = $pairs;
		$od->order();
		$this->tuples = $od->tuples;
	}
	
	function parse($t)
	{
		$list = explode(' ',$t);
		$field = array_shift($list);
		$reg = array_shift($list);
		$rest = join(' ',$list);
		$this->parse2($field,$reg,$rest);
	}
	
	function parse2($field, $reg, $labels)
	{
		$list = explode(',', $labels);
		$list2 = array();
		foreach($list as $l)
		{
			$list2[] = trim($l);
		}
		$k = count($list2);
		
		$reg = '/'.$reg.'/';
		
		$c = count($this->tuples);
		
		$newtuples = array();
		for ($i=0;$i<$c;$i++)
		{
			$tp = $this->tuples[$i];
			$d = $tp->fields();
			$v = $d[$field];
			
			
			
			if (preg_match($reg,$v,$matches))
			{
				for($j=0;$j<$k;$j++)
				{
					$d[$list2[$j]] = $matches[$j+1];
				}	
			}
			else
			{
				for($j=0;$j<$k;$j++)
				{
					$d[$list2[$j]] = '';
				}	
			}
			$tp = new swTuple($d); 
			$newtuples[$tp->hash()] = $tp;		
						
		}
		$this->tuples = $newtuples;
	}
	
	function print2()
	{
		$c = count($this->header);
		for ($i=0;$i<$c;$i++)
		{
			$widths[] = stren($this->header[$i]);
		}
		foreach($this->tuples as $t)
		{
			$widths[$i] = max($widths[$i], stren($t->value[$i]));
		}
		$firstline = str_repeat('-',sum($widths)+count($widths)-1);
		
		$lines = array();
		$lines[]= $firstline;
		
		$fields = array();
		for($i=0;$i<$c;$i++)
		{
			$fields[]= str_pad($header[$i],$widths[$i],' ');
		}
		$lines[] = join(' ',$fields);
		
		foreach($this->tuples as $t)
		{
			$fields = array();
			for($i=0;$i<$c;$i++)
			{
				$fields[]= str_pad($t->value($header[$i]),$widths[$i],' ');
			}
			$lines[] = join(' ',$fields);
		}
		
		return join(PHP_EOL,$lines);
		
	}
	
	function project($t)
	{
		if (substr($t,0,6)=='inline')
		{
			$r2 = $this->doClone();
			$r2->project(substr($t,7));
			if (count(array_intersect($this->header,$r2->header))>0)
				$this->join($r2,'natural');
			else
				$this->join($r2,'cross');
			return;
		}
		
		if (substr($t,0,6)=='rollup')
		{
			// check aggregators, there must be only one per field
			$t2 = substr($t,7);
			$pairs = explode(',',$t2);
			$c = count($pairs);
			$rolluppairs = array();
			$rollupfields = array();
			$rollupremoved = array();
			for($i=0;$i<$c;$i++)
			{
				$p = trim($pairs[$i]);
				if ($p == '')
			 		throw new swRelationError('Project rollup empty pair',11);
				$fields = explode(' ',trim($p));
				$f = $fields[0];
				if (in_array($f, $rollupfields))
					throw new swRelationError('Project rollup duplicate column '.$f,11);
				$rollupfields[] = $f;
				$tr = str_replace(' ','_',$p).' '.$f;
				$rolluppairs[] = $tr;
				$rollup[] = $p;				 
			}
			$r2 = $this->doClone();
			$found = true;
			while ($found)
			{
				//print_r($rollup);
				$r2->project2($rollup);
				
				// add removed columns
				//print_r($rollupremoved);
				foreach($rollupremoved as $f)
				{
					//echo "extend ".$f;
					$r2->extend2($f,'" "');
				}
								
			 	$r2->rename12($rolluppairs);
			 	$this->union($r2);
			 	
			 	// remove each time a column
			 	$found = false;
			 	$c = count($rollup);
			 	for($i=$c-1;$i>=0;$i--)
			 	{
				 	$p = trim($rollup[$i]);
				 	//echo $p.','; 
				 	if (strpos($p,' ')===FALSE) 
				 	{
					 	//echo "found";
					 	$found = true;
					 	$rollupremoved[] = $rollup[$i];
					 	unset($rollup[$i]);
					 	$rollup = array_values($rollup);
					 	$i=0; // exit for i
				 	}
				 	//print_r($rollup);
			 	}
			}
			return;
		}
		$pairs = explode(',',$t);
		$this->project2($pairs);
	}
	
	function project2($pairs)
	{	
		$columns = array();
		$stats = array();
		$hasstats = false;
		$newcolumns = array();
		foreach($pairs as $p)
		{
			$fields = explode(' ',trim($p));
			$columns[] = $fields[0];
			if (count($fields) >= 2)
			{
				$stats[] = $fields[1];
				$nc = $fields[0].'_'.$fields[1];
				$haststats = true;
			}
			else
			{
				$stats[] = '';
				$nc = $fields[0];
			}
			if (in_array($nc, $newcolumns))
				throw new swRelationError('Project duplicate column '.$nc,141);
			if (array_key_exists($fields[0],$this->formats))
				$this->formats[$nc] = $this->formats[$fields[0]];
			$newcolumns[] = $nc;
		}
		$c = count($columns);
		 
		foreach($columns as $s)
		{
			if (!in_array($s, $this->header))
				throw new swRelationError('Project unknown column '.$s,141);
		}
		$newtuples = array();
		//$k = count($this->tuples);
		foreach($this->tuples as $tp)
		//$j = 0;
		//for($j=0;$j<$k;$j++)
		{
			//if ($j>=$k) continue; $j++;
			//$tp = $this->tuples[$j];
			$d = array();
			for ($i=0;$i<$c;$i++)
			{
				if ($stats[$i]=='')
					$d[$newcolumns[$i]] = $tp->value($columns[$i]);
			}
			//print_r($d);
			//print_r($stats);
			$tp2 = new swTuple($d);
			$h = $tp2->hash();
			if (array_key_exists($h,$newtuples))
			{
				$d = $newtuples[$h];
			}
			else
			{
				for($i=0;$i<$c;$i++)
				{
					if ($stats[$i] != '')
					{
						if (array_key_exists($stats[$i], $this->aggregators))
						{
							$a = $this->aggregators[$stats[$i]];
							$a = $a->doClone();
							$a->functions = $this->functions;
							$d[$newcolumns[$i]] = $a;
						}
						else
						{
							$d[$newcolumns[$i]] = new swAccumulator($stats[$i]);
						}
					}
				}
			}
			for($i=0;$i<$c;$i++)
			{
				if ($stats[$i] != '')
				{
					$a = $d[$newcolumns[$i]];
					$a->add($tp->value($columns[$i]));
					$d[$newcolumns[$i]] = $a;
				}
			}
			$newtuples[$h] = $d;
		}
		//print_r($newtuples);
		
		$this->header = $newcolumns;
		$this->tuples = array();
		
		foreach($newtuples as $t)
		{
			$d2 = array();
			foreach($t as $k=>$v)
			{
				if (is_a($v,'swAccumulator'))
				{
					$d2[$k] = $v->reduce();
				}
				else
				{
					$d2[$k] = $v;
				}
			}
			$tp = new swTuple($d2);
			$this->tuples[$tp->hash()] = $tp;
		}
		
		// special case no tuple, still have count and sum
		
		if (count($this->tuples) == 0)
		{
			$d2 = array();
			for($i=0;$i<$c;$i++)
			{
				switch($stats[$i])
				{
					case 'count' : $d2[$newcolumns[$i]] = 0; break;
					case 'sum' : $d2[$newcolumns[$i]] = 0; break;
				}
			}
			if (count($d2)>0)
			{
				$tp = new swTuple($d2);
				$this->tuples[$tp->hash()] = $tp;
			}
		}	
	}
	
	function rename1($t)
	{
		$pairs = explode(',',$t);
		$this->rename12($pairs);
	}
	
	function rename12($pairs)
	{
		$dict = array();
		//print_r($this->header);
		foreach($pairs as $p)
		{
			$fields = explode(' ',trim($p));
			if (count($fields) != 2)
				throw new swRelationError('Invalid rename '.$p,121);
			if (! in_array($fields[0], $this->header))
				throw new swRelationError('Unknown rename '.$fields[0],122);
			$i = array_search($fields[0], $this->header);
			unset($this->header[$i]);
			$this->addColumn($fields[1]);
			$this->header = array_values($this->header);
			$dict[$fields[0]] = $fields[1];
		}
		//print_r($this->header);
		$c = count($this->tuples);
		$newtuples = array();
		foreach($this->tuples as $tp)
		{
			$d = $tp->fields();
			
			foreach($dict as $k=>$v)
			{
				$val = $d[$k];
				unset($d[$k]);
				$d[$v] = $val;				
			}
			$tp = new swTuple($d);
			$newtuples[$tp->hash()] = $tp;
		}
		$this->tuples = $newtuples;
	}
	
	function select1($condition)
	{
		$xp = new swExpression($this->functions);
		$xp->compile($condition);
		
		$i = 0;
		foreach($this->tuples as $k=>$tp)
		{
			$d = $tp->fields();
			$locals['rownumber'] = $i+1;
			$result = $xp->evaluate($d,$this->globals,$this->locals);
			if ($result == '0')
			{
				unset($this->tuples[$k]);
			}
			$i++;			
		}
	}
	
	function serialize1()
	{
		if (count($this->header)<2)
			throw new swRelationError('Serialize less than 2 properties',602);
		$observationkey = $this->header[0];
		$n = count($this->header);
		$list = array();
		for($i=1;$i<$n;$i++)
		{
			$list[]= $this->header[$i];
		}
		
		$list2[0] = $observationkey;
		$list2[1] = 'key';
		$list2[2] = 'value';
		if ($observationkey == 'key') $list2[1] = 'key0';
		if ($observationkey == 'value') $list2[1] = 'value0';
		
		$r = new swRelation($list0);
		
		$m = count($this->tuples);
		for($j=0;$j<$m;$j++)
		{
			$tp = $this->tuples[$j];
			
			for($i=1;$i<$n;$i++) // 0 or 1???
			{
				$d = array();
				$d[$list2[0]] = $tp->value($observationkey);
				$d[$list2[1]] = $list[$i];
				$d[$list2[2]] = $tp->value($list[$i]);
				$tp2 = new swTuple($d);
				$r->tuples[$tp2->hash()] = $tp2;				
			}
		}		
		$this->header = $list2;
		$this->tuples = $r->tuples;		
	}
	
	function getCSV()
	{
		$lines = array();
		$c = count($this->header);
		
		$lines[] = join(',',$this->header);
		
		foreach($this->tuples as $tp)
		{
			$fields = array();
			for($i=0;$i<$c;$i++)
			{
				$f = $this->header[$i];
				$test = $tp->value($f);
				if (array_key_exists($f,$this->formats))
				{
					$fm = $this->formats($f);
					if ($fm != '')
						$test = $this->format2($floatval($test),$fm);
				}
				if (is_numeric($test))
					$fields[] = $test;
				else
					$fields[] = '"'.str_replace('"','""', $test).'"';
				
			}
			$lines[] = join(',',$fields);
		}
		return join(PHP_EOL,$lines);
	}
	
	function setCSV($lines,$pad = false)
	{
		$this->header = array();
		$this->tuples = array();
		$separator = ';';
		$firstline = true;
		
		$k = count($lines);
		for($j=0;$j<$k;$j++)
		{
			$line = $lines[$j];
			if ($line == '') continue;
			if ($firstline && substr($line,0,1)=='#') continue;
			if ($firstline)
			{
				//echo $line; echo strpos($line,$separator); echo "k";
				if (strpos($line,$separator)===FALSE) $separator = ','; 
				$fields = explode($separator,$line);
				foreach($fields as $field)
					$this->addColumn($this->cleanColumn($field));
				$c = count($fields);
				$firstline = false;				
			}
			else
			{
				$fields = array();
				$state = 'start';
				$acc = '';
				$quoted = false;
				$chlist = str_split($line); 
				foreach($chlist as $ch)
				{
					//echo $state.' '.$ch.' '; echo $separator;
					
					switch ($state)
					{
						case 'start' :	switch($ch)
										{
											case '"': $acc='';$quoted=true;$state='quote';break;
											case $separator: if($quoted) $fields[]=$acc;
															 else $fields[]=trim($acc);
															 $acc='';$quoted = false;break;
											default: if (!$quoted) $acc.=$ch;
										}
										break;
						case 'quote' :	switch($ch)
										{
											case '"':$state='quotesuspend';break;
											default: $acc.=$ch;
										}
										break;
						case 'quotesuspend' : switch($ch)
										{
											case '"':$acc.='"';$state='quote';break;
											case $separator: if($quoted) $fields[]=$acc; 
															 else $fields[]=trim($acc);
															 $acc='';$quoted = false;
															 $state='start';break;
											default: $state = 'start';
										}
										break;
					}
				}
				
				switch ($state)
				{
					case 'start' : if($quoted) $fields[]=$acc;
								   else $fields[]=trim($acc); break;
					case 'quote' : // 'error missing end quote, we need to read the following line
									if ($j<$k-1)
									{
										$lines[$j].=' '.$lines[$j+1];
										unset($lines[$j+1]);
										$k -= 1;
										$j -= 2;
										continue;
									}
									else
									{
										if($quoted) $fields[]=$acc;
										else $fields[]=trim($acc); 
									}
									break;
					case 'quotesuspend' : 	if($quoted) $fields[]=$acc;
											else $fields[]=trim($acc); 
				}
				$c = count($fields);
				$d = array();
				//echo $line;
				//print_r($fields);
				//print_r($this->header);
				if ($c == count($this->header))
				{
					for($i=0;$i<$c;$i++)
						$d[$this->header[$i]] = $fields[$i];
				}
				else
				{
					if (!$pad)
						throw new swRelationError('Read CSV field count in row not header count (line: '.($j+1).'", header: '.$count($this->header).', fields:'.($c+1).')',99);
					for($i=0;$i<count($this->header);$i++)
					{
						$d[$this->header[$i]] = '';
					}
					for($i=0;$i<$c;$i++)
					{
						$d[$this->header[$i]] = $fields[$i];
					}
				}
				$tp = new swTuple($d);
				$this->tuples[$tp->hash()] = $tp;
			}
		}
		
	}
	
	function getTab()
	{
		$lines = array();
		$c = count($this->header);
		
		$lines[] = join("\t",$this->header);
		
		foreach($this->tuples as $tp)
		{
			$fields = array();
			for($i=0;$i<$c;$i++)
			{
				$f = $this->header[$i];
				$test = $tp->value($f);
				if (array_key_exists($f,$this->formats))
				{
					$fm = $this->formats($f);
					if ($fm != '')
						$test = $this->format2($floatval($test),$fm);
				}
				$fields[] = $test;
				
			}
			$lines[] = join("\t",$fields);
		}
		return join(PHP_EOL,$lines);
	}
	
	function setTab($lines)
	{
		$this->header = array();
		$this->tuples = array();
		
		$firstline = true;
		foreach($lines as $line)
		{
			$fields = explode("\t",$lines);
			if ($firstline)
			{
				foreach($fields as $field)
				{
					$this->addColumn($field);
				}
				$c = count($fields);
				$firstline = false;
			}
			else
			{
				$d = array();
				$i=0;
				foreach($fields as $field)
				{
					if ($i>=$c) continue;
					$d[$this->header[$i]] = $field;
					$i++;
				}
				while($i<=$c)
				{
					$d[$this->header[$i]] = '';
					$i++;
				}
				$tp = new swTuple($d);
				$this->tuples[$tp->hash()] = $tp;
				
			}
		}
	}
	
	function getJSON()
	{
		$lines = array();
		
		$c = count($this->header);
		
		foreach($this->tuples as $tp)
		{
			$pairs = array();
			for($i=0;$i<$c;$i++)
			{
				$f = $this->header[$i];
				$test = $tp->value($f);
				if (array_key_exists($f,$this->formats))
				{
					$fm = $this->formats($f);
					if ($fm != '')
						$test = $this->format2($floatval($test),$fm);
				}
				if (is_numeric($test))
					$pairs[] = '"'.$f.'" : '.$test;
				else
					$pairs[] = '"'.$f.'" : "'.str_replace('"','""', $test).'"';
				
			}
			$lines[] = '{'.join(',',$pairs).'}';
		}
		return '{ "relation" : ['.PHP_EOL.join(','.PHP_EOL,$lines).PHP_EOL.']}';
	}
	
	function setJson($s)
	{
		throw new swRelationError('Read JSON not yet supported',77);
		// TO DO
	}
	
	function toChart($options)
	{
		// TO DO
	}
	
	function toFields($limit = 0)
	{
		$lines = array();
		
		foreach($this->tuples as $tp)
		{
			
			$d = $tp->fields();
			
			foreach($d as $k=>$v)
			{
				$lines[] = '[<nowiki>[</nowiki>'.$k.'::'.$v.']]';
			}
			
			$lines[] = '';
		}
		$result = PHP_EOL.join(PHP_EOL,$lines).PHP_EOL;
		return $result;
		
	}
	
	function toHTML($limit = 0)
	{
		$lines = array();
		$lines[]= '{| class="datatable" ';
		
		$k = count($this->header);
		$line = '! '.join(' !! ',$this->header);
		$lines[]= '|-';
		$lines[]= $line;
		
		$c = count($this->tuples);
		$i0 = 0;
		if ($limit>0 && $limit<$c)
		{ 	
			$c = $limit;
			$i0 = 0;
		}
		elseif($limit <0 && -$limit<$c)
		{
			$i0 = $c + $limit; // to test
		}
		
		$i = 0;
		foreach($this->tuples as $tp)
		{
			if ($i<$i0) { $i++; continue;}
			if ($i>=$c) continue; 
			$i++;
			
			$fields = array();
			for($j=0;$j<$k;$j++)
			{
				$f = $this->header[$j];
				$t = $tp->value($f);
				$td = '';
				if (array_key_exists($f, $this->formats))
				{
					$fm = $this->formats[$f];
					if ($fm != '')
					{
						$t = $this->format2($t,$fm);
						$td = ' style="text-align:right" | '; 						
					}
				}
				if (!$t) $t = ' ';  // empty table cell would collapse
				$fields[]=$td.$t;				
			}
			$line = '| '.join(' || ',$fields);
			$lines[] = '|-';
			$lines[] = $line;
		}
		$lines[] = '|-';
		$lines[] = '|}';
		$result = PHP_EOL.join(PHP_EOL,$lines).PHP_EOL;
		return $result;
		
	}
	
	function toTemplate($tmp)
	{	
		$lasti = 0;
		$lastvalidi = 0;
		$selectors = array();
		$templates = array();
		$dicts = array();
		$selectors[] = '';
		do
		{
			$i = strpos($tmp,'{{',$lasti);
			if ($i !== FALSE)
			{
				$i2 = strpos($tmp,'}}',$i);
				if ($i2 >= 0)
				{
					$sel = substr($tmp,$i+2,$i2-$i-2);
					if (substr($sel,0,6) == 'group ')
					{
						$sel2 = $sel;
						$sel = 'group';
					}
					//echo $sel.$lasti.' '.$i.$i2.";";
					switch($sel)
					{
						case 'first':
						case 'each':
						case 'last':
						case 'end':		$selectors[] = $sel;
										$templates[] = trim(substr($tmp,$lastvalidi,$i-$lastvalidi)).PHP_EOL;
										$lastvalidi = $i2+2; 			
										break;
						case 'group':	$selectors[] = $sel2;
										$templates[] = trim(substr($tmp,$lastvalidi,$i-$lastvalidi)).PHP_EOL; 
										$lastvalidi = $i2+2; 
										break;
						default:		//ignore					
					}
					$lasti = $i2+2;
						
				}
			} 
			//if(count($selectors)>3) echo '<br>'.$lasti.' '.print_r($selectors);
			
		} while($i !== FALSE) ;
		
		if (count($selectors)>0)
			$templates[] = substr($tmp,$lastvalidi);
		
		foreach($this->tuples as $tp)
		{
			$d = $tp->fields();
			foreach($this->header as $f)
			{
				$t = $d[$f];
				if (array_key_exists($f,$this->formats))
				{
					$fm = $this->formats[$f];
					$d[$f]  = $this->format2($t,$fm);
				}
			}
			$dicts[] = $d;
		}
		$c = count($dicts);
		$k = count($this->header);
		$result = '';
		
		//print_r($selectors);
		//print_r($templates);
		
		for($i=0;$i<$c;$i++)
		{
			$d = $dicts[$i];
			if ($i>0) $lastd = $dicts[$i-1]; else $lastd = null;
			if ($i<$c-1) $nextd = $dicts[$i+1]; else $lastd = null;
			
			$eachhashappened = false;
			$m = count($selectors);
			for($l=0;$l<$m;$l++)
			{
				$selt = $selectors[$l];
				$line = $templates[$l];
				//echo $selt.' '.$line.'  ';
				
				switch($selt)
				{
					case 'first' :	if ($i==0)
									{
										foreach($this->header as $f)
										{
											$line = str_replace('{{'.$f.'}}',$d[$f],$line);
										}
										$result.= $line;
									}
									break;
					case 'each' :	foreach($this->header as $f)
									{
										$line = str_replace('{{'.$f.'}}',$d[$f],$line);
									}
									$result.= $line;
									$eachhashappened = true;
									break;
					case 'last' :	if ($i==$c-1)
									{
										foreach($this->header as $f)
										{
											$line = str_replace('{{'.$f.'}}',$d[$f],$line);
										}
										$result.= $line;
									}
									break;
					case 'end'	:	//ignore
									break;
					default		:	if (substr($selt,0,6)=='group ')
									{
										
										$line = $templates[$l];
										$key = trim(substr($selt,6));
										//echo $key;
										if (array_key_exists($key,$d))
										{
											if (!$eachhashappened) // must be first
											{
												if ($i==0 or $lastd[$key] != $d[$key])
												{
													foreach($this->header as $f)
													{
														$line = str_replace('{{'.$f.'}}',$d[$f],$line);
													}
													$result.= $line;
												}
											}
											else // must be last
											{
												
												if ($i==0 or $nextd[$key] != $d[$k])
												{
													foreach($this->header as $f)
													{
														$line = str_replace('{{'.$f.'}}',$d[$f],$line);
													}
													$result.= $line;
												}
											}
										}
									}
				}
			}
		}
		return $result;
	}
	
	
	function union($r)
	{
		$e = $this->emptyTuple();
		
		$c = count($r->tuples);
		
/*
		echo "union ";
		print_r($e); 
		echo " with ";
		print_r($r->tuples);
*/
		
		
		foreach($r->tuples as $tp)
		{
			if ($tp->sameFamily($e))
				$this->tuples[$tp->hash()] = $tp;
			else
				throw new swRelationError('Union different columns',302);
		}
	}
	
	function update($condition, $label, $expression)
	{
		$xp = new swExpression($this->functions);
		$xp->compile($condition);
		$xp2 = new swExpression($this->functions);
		$xp2->compile($expression);
		
		$c = count($this->tuples);
		for($i=0;$i<$c;$i++)
		{
			$tp = $this->tuples[$i];
			$d = $tp->fields();
			if ($xp->evaluate($d,$this->globals,$this->locals))
			{
				$d[$label] = $xp2->evaluate($d,$this->globals,$this->locals);
				unset($this->tuples[$tp->hash()]);
				$tp = new swTuple($d);
				$this->tuples[$tp->hash()]=$tp;
				// there may be a better solution in place.
			}
			
		}
	}

		
	function validName($s)
	{
		return (strlen($s)< 31 and preg_match('/^[A-Za-z][A-Za-z0-9_]*$/',$s)); 
	}
}

class swTuple
{
	var $pfields = array();
	var $phash;
	
	function __construct($list)
	{
		$keys = array();
		$values = array();
		$this->pfields = array_clone($list);
		$keys = array_keys($list);
		sort($keys);
		foreach($keys as $k)
		{
			$values[$k] = $list[$k]; 
		}
		$this->phash = md5(join(PHP_EOL,$values));
	}
	
	function arity()
	{
		return count($this->pfields);
	}
	
	function fields()
	{
		return array_clone($this->pfields);
	}
	
	function hash()
	{
		return $this->phash;
	}
	
	function hasKey($k)
	{
		return array_key_exists($k, $this->pfields);
	}
	
	function sameFamily($t)
	{
		if ($this->arity() != $t->arity()) return false;
		foreach($this->pfields as $k=>$e)
		{
			if (!array_key_exists($k, $t->pfields)) 
				return false;
		}
		return true;
	}
	function value($s)
	{
		$result = @$this->pfields[$s];
		return $result;
	}
	
}

class swOrderedDictionary
{
	var $pairs = array();
	var $tuples = array();
	
	function order()
	{
		uasort($this->tuples, function($a,$b) { return $this->compare($a, $b) ; } );
	}
	
	function compare($a, $b)
	{
		$atp = $a;
		$btp = $b;
		$adict = $atp->fields();
		$bdict = $btp->fields();
		
		foreach($this->pairs as $p)
		{
			$fields = explode(' ',trim($p));
			if (count($fields < 2)) $fields[] = 'A';
			if (! array_key_exists($fields[0],$adict) 
				|| ! array_key_exists($fields[0],$bdict) ) 
				throw new swRelationError('Dict Compare missing field '. $fields[0],609);
			
			$atext = $adict[$fields[0]];
			$btext = $bdict[$fields[0]];
						
			switch ($fields[1])
			{
				case 'A' : $test = strcmp($atext,$btext); if ($test != 0) return $test; break;
				case 'Z' : $test = strcmp($atext,$btext); if ($test != 0) return -$test; break;
				case 'a' : $test = strcasecmp($atext,$btext); if ($test != 0) return $test; break;
				case 'z' : $test = strcasecmp($atext,$btext); if ($test != 0) return -$test; break;
				case '1' :  if (floatval($atext) > floatval($btext)) return 1;
							if (floatval($atext) < floatval($btext)) return -1;
							break;
				case '9' : if (floatval($atext) > floatval($btext)) return -1;
							if (floatval($atext) < floatval($btext)) return 1;
							break;
				default: 	throw new swRelationError('Invalid order parameter',501);

			}
		}
		return 0;
		
	}
	
}

class swAccumulator
{
	var $list = array();
	var $method;
	var $label;
	var $source;
	var $offset;
	var $functions;
	
	
	function add($t)
	{
		$this->list[] = $t;
	}
	
	function doClone()
	{
		$a = new swAccumulator($this->method);
		if ($this->method = 'custom')
		{
			$a->label = $this->label;
			$a->source = $this->source;
			$a->offset = $this->offset;
		}
		return $a;	
	}
	
	function __construct($m)
	{
		$this->method = $m;
	}
	
	private function pAvg()
	{
		if (count($this->list)==0) return "";
		$acc = 0;
		foreach($this->list as $t)
			$acc += floatval($t);
		$v = $acc / count($this->list);
		return cText12($v);
	}
	
	private function pConcat()
	{
		return join('',$this->list);
	}
	
	private function pCount()
	{
		return count($this->list);
	}
	
	private function pCustom()
	{
		$xp = new swExpressionCompiledFunction($label, $source, $offset, true);
		$result = 'result';
		$elem = 'elem';
		$xp->fndict = $this->functions;
		return $xp->DoRunAggregator($this->list);
	}
	
	private function pMax()
	{
		if (count($this->list)==0) return "";
		$acc = floatval(array_shift($this->list));
		foreach($this->list as $t)
			if (floatval($t) > $acc)
				$acc = floatval($t);
		return cText12($acc);
	}
	
	private function pMaxS()
	{
		if (count($this->list)==0) return "";
		$acc = array_shift($this->list);
		foreach($this->list as $t)
			if (strcasecmp($t,$acc) > 0)
				$acc = $t;
		return $acc;
	}
	
	private function pMedian()
	{
		if (count($this->list)==0) return "";
		$acc = array();
		foreach($this->list as $t)
			$acc[] = floatval($t);
		sort($acc,SORT_NUMERIC);
		if (count($acc) % 2 != 0)
			$v = $acc[(count($acc)-1)/2];
		else
			$v = $acc[count($acc)/2] + $acc[count($acc)/2-1];
		return cText12($v);
	}
	
	private function pMedianS()
	{
		if (count($this->list)==0) return "";
		asort($this->list);
		if (count($this->list) % 2 != 0)
			$v = $this->list[(count($this->list)-1)/2];
		else
			$v = $this->list[(count($this->list)/2)];
		return $v;
	}
	
	private function pMin()
	{
		if (count($this->list)==0) return "";
		$acc = floatval(array_shift($this->list));
		foreach($this->list as $t)
			if (floatval($t) < $acc)
				$acc = floatval($t);
		return cText12($acc);
	}
	
	private function pMinS()
	{
		if (count($this->list)==0) return "";
		$acc = array_shift($this->list);
		foreach($this->list as $t)
			if (strcasecmp($t,$acc) < 0)
				$acc = $t;
		return $acc;
	}
	
	private function pStdev()
	{
		if (count($this->list)==0) return "";
		$acc = 0;
		$acc2 = 0;
		foreach($this->list as $t)
		{
			$acc += floatval($t);
			$acc2 += floatval($t) * floatval($t);
		}
		$v = sqrt($acc2/count($this->list) - $acc/count($this->list)*$acc/count($this->list));
		return cText12($v);
	}
	
	private function pSum()
	{
		if (count($this->list)==0) return "";
		$acc = 0;
		foreach($this->list as $t)
			$acc += floatval($t);
		return cText12($acc);
	}
	
	function reduce()
	{
		switch ($this->method)
		{
			case 'count'	: 	return $this->pCount();
			case 'sum'  	:	return $this->pSum();
			case 'avg'  	:	return $this->pAvg();
			case 'min'  	:	return $this->pMin();
			case 'max'  	:	return $this->pMax();
			case 'median'  	:	return $this->pMedian();
			case 'mins'  	:	return $this->pMinS();
			case 'maxs'  	:	return $this->pMaxs();
			case 'medians'  :	return $this->pMedianS();
			case 'stddev'  	:	return $this->pStdev();
			case 'concat'  	:	return $this->pConcat();
			case 'custom'  	:	return $this->pCustom();
		}
	}
	
}



class swRelationError extends Exception	
{
	
}

function array_clone($arr) { return array_slice($arr, 0, null, true); }

function swNumberformat($d,$f)
{	
	if (substr($f,0,1)=='n')
	{
		$decimals = substr($f,1,1);
		$decpoint = substr($f,2,1);
		$thousandsep = substr($f,3,1);
		if ($decpoint == '' ) $decpoint = '.';
		if ($thousandsep == '' ) $thousandsep = "'";
		if ($thousandsep == 's' ) $thousandsep = ' ';
		return number_format($d,$decimals,$decpoint,$thousandsep);
	}
	return sprintf($f,$d); // waiting for excel style format
}

function cText12($d)
{        
    $a;
	$t;
	$s;


	$a = abs($d);
	
	if ($a > 10.0e12)
		// return format(d,"-0.000000000000e").ToText
		return sprintf('%1.12e+2',$d);
	elseif ($a < 10.0e-300)
		return "0";
	elseif ($a < 10.0e-12)
		// return format(d,"-0.000000000000e").ToText
		return sprintf('%%1.12e+2',$d);
	
	// s = format(d,"-0.##############")
	$s = sprintf('%1.12f',$d);
	
	if (strlen($s)>12)
		// s = format(round(d*10000000000000000)/10000000000000000,"-0.##############")
		$s = sprintf('%1.12f',round($d*10000000000000)/10000000000000);
		
	$s = TrimTrailingZeroes($s);
		
	if (substr($s,-1)==".")
		$s = substr(s,0,-1);
	
	return $s;
	
}


?>