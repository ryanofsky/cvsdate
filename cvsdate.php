<?

function showhelp()
{?>
cvsdate.php is a small utitilty that sets the revision dates of files stored
in a CVS repository to the modification dates of a set of corresponding files
in another directory (which is probably, but not neccessarily, a CVS working
directory).

Usage: php cvsdate.php [OPTION] repository working [tag [antitag]]

Options:

  -f, --force    Change dates in the repository even if they are earlier than
                 the ones they are being replaced with.
                 
  -h, --help     Display this help message and quit.
  
Arguments:

  repository     The complete path of the CVS repository to change dates in.
  
  working        The complete path of a directory which contains files that
                 are also stored in the CVS repository. Under conditions
                 specified by other arguments, the file modification times of
                 files in (and under) this directory will be saved in the CVS
                 repository as the commit dates of particular file revisions.
                 
  tag            The symbolic tag name of file revisions in the repository
                 that will have their commit dates replaced by the file
                 modification dates. This is an optional parameter. If it
                 is not specified, cvsdate will update the dates of the
                 HEAD revisions.
                 
  antitag        A symbolic tag name of file revisions in the repository
                 that will NOT have their commit dates replaced (under any
                 circumstances). This is an optional parameter. A practical
                 example of its use is shown in the documentation.

cvsdate makes chages directly to the cvs repository, and should not be used
while the cvs server is running. 
<?
}

function showwarning($text)
{
  global $warned;
  $warned = true;
  print(wordwrap("Warning: $text"));
}

///////////////////////////////////////////////////////////////////////////////

function seek($fp, $startpos, $filesize)
{
  global $buffer, $buffer_start, $buffer_size;

  $desired_start = (int)floor($startpos / $buffer_size) * $buffer_size;

  if ($desired_start != $buffer_start)
  {
    $buffer_start = $desired_start;
    fseek($fp,$buffer_start);
    $buffer = fread($fp,$buffer_size);
  }  
  
  $p = $startpos;

  $inside_section = false;
  $inside_header = false;
  $inside_quote = false;
  $inside_escape = false;
  $first_section = false;

  $startpos = $endpos = $starthead = $endhead = $p; // return values;
  $header = "";
  
  for(;;)
  {
    for(;$p - $buffer_start < $buffer_size; ++$p)
    {
      $c = $buffer[$p - $buffer_start];
      $ca = ord($c);
      $is_whitespace = $ca <= 32;
      $is_num = (48 <= $ca && $ca <= 57) || $ca == 46;
      $is_quot = $c == '@';
      $is_term = $c == ';';
      
      if ($inside_header && ($is_whitespace || $is_quot || $is_term))
      {
        if ($is_numeric_header || $header == "desc")
          break 2;
        else
        {
          $inside_section = true;
          $inside_header = false;
        }  
      }
      
      if ($p >= $filesize)
      {
        if ($inside_quote)
          showwarning("File ends in the middle of a quote.");
        break 2;
      }

      if ($inside_quote)
      {
        if ($is_quot)
          $inside_escape = !$inside_escape;
        else if ($inside_escape)
          $inside_quote = $inside_escape = false;
      }
      else if ($is_quot)
        $inside_quote = true;
      
      if (!$inside_quote && !$is_quot)
      {
        if ($inside_section)
        {
          if ($is_term) $inside_section = false;
        }
        else // !$inside_section
        {
          if (!$is_whitespace)
          {
            if (!$inside_header)
            {
              $inside_header = true;
              $is_numeric_header = $is_num;
              $starthead = $p;
              $header = $c;
            }
            else
            {
              $is_numeric_header = $is_numeric_header && $is_num;
              $header .= $c;
            } 
          } // is_whitespace
        }  
      
        if ($inside_quote || !$is_whitespace)
        {
          if (!$first_section)
          {
            $first_section = true;
            $startpos = $endpos = $p;
          }
          else if (!$inside_header)
            $endpos = $p;
        }
      }
    }
    $buffer_start += $buffer_size;
    fseek($fp,$buffer_start);
    $buffer = fread($fp,$buffer_size);
  }
  if ($inside_header) $endhead = $p; else $endhead = $starthead;
  return array($startpos,$endpos,$starthead,$endhead,$header);
}

///////////////////////////////////////////////////////////////////////////////

function parsehead($fp, $startpos, $endpos, $before, $target)
{
  global $buffer, $buffer_start, $buffer_size;

  $desired_start = (int)floor($startpos / $buffer_size) * $buffer_size;

  if ($desired_start != $buffer_start)
  {
    $buffer_start = $desired_start;
    fseek($fp,$buffer_start);
    $buffer = fread($fp,$buffer_size);
  }  
  
  $p = $startpos;

  $inside_section = false;
  $inside_header = false;
  $inside_quote = false;
  $inside_escape = false;
  $inside_symbols = false;
  $inside_revision = false;
  $inside_before = false;
  $inside_target = false;
  $inside_head = false;
  $beforerev = $targetrev = $head = $temp = "";
  
  for(;;)
  {
    for(;$p - $buffer_start < $buffer_size; ++$p)
    {
      $c = $buffer[$p - $buffer_start];
      $ca = ord($c);
      $is_whitespace = $ca <= 32;
      $is_quot = $c == '@';
      $is_term = $c == ';';
      $is_num = (48 <= $ca && $ca <= 57) || $ca == 46;

      if ($inside_header && ($is_whitespace || $is_quot || $is_term))
      {
        $inside_header = false;
        $inside_section = true;
        $inside_symbols = $temp == "symbols";
        $inside_head = $temp == "head";
        $temp = "";
      }
      
      if ($p >= $endpos)
      {
        break 2;
      }

      if ($inside_quote)
      {
        if ($is_quot)
          $inside_escape = !$inside_escape;
        else if ($inside_escape)
          $inside_quote = $inside_escape = false;
      }
      else if ($is_quot)
        $inside_quote = true;
      
      if (!$inside_quote && !$is_quot)
      {
        if ($inside_section)
        {
          if ($is_term)
            $inside_section = false;

          if ($inside_head)
          {
            if ($is_num && $inside_section)
              $temp .= $c;
            else if (!$inside_section || strlen($temp))
            {
              $inside_head = false;
              $head = $temp;
              $inside_head = false;
            }
          }
          
          if ($inside_symbols)
          {
            if (!$is_whitespace && $inside_section)
            {
              if ($c == ':') 
              {
                $inside_before = $temp == $before;
                $inside_target = $temp == $target;
                $inside_revision = true;
                $temp = "";
              }  
              else
                $temp .= $c;
            }
            else
            {
              if ($inside_before)
              {
                $inside_before = false;
                $beforerev = $temp;
              }
              
              if ($inside_target)
              {
                $inside_target = false;
                $targetrev = $temp;
              }
              
              $inside_revision = false;
              $temp = "";
              
              if (!$inside_section) break 2;
            }
          }
        }
        else // !$inside_section
        {
          if (!$is_whitespace)
          {
            if (!$inside_header)
            {
              $inside_header = true;
              $temp = $c;
            }
            else
              $temp .= $c;
          }
        }  
      }
    }
    $buffer_start += $buffer_size;
    fseek($fp,$buffer_start);
    $buffer = fread($fp,$buffer_size);
  }
  return array($beforerev,$targetrev,$head);
}

///////////////////////////////////////////////////////////////////////////////

function DoFolder($folder = "")
{
  global $WORKING, $REPOSITORY, $BEFORE, $TARGET, $FORCE, $buffer_start;
  
  $latest = 0;
  
  if ($folder) $folder .= "/";
  
  $folderobj = opendir("$WORKING/$folder") or die ("\n\nOh no! opendir(\"$WORKING/$folder\") failed!\n\n");
 
 
  while (($name = readdir($folderobj)) !== false)
  if ($name !== "." && $name !== ".." && $name !== "CVS")
  {
    $fname = "$folder$name";
    $cname = "$WORKING/$fname";
    $timestamp = 0;
    if (is_dir($cname))
      DoFolder($fname);
    else
    {
      $date = filemtime($cname);
      
      print("$fname ... ");
      
      if (file_exists($n = "$REPOSITORY/$fname,v")) { }
      else if (file_exists($n = "$REPOSITORY/{$folder}Attic/$name,v")) { }
      else $n = "";
      
      if ($n)
      {
        $fs = filesize($n);
        $fp = fopen($n, "r+b");
        $buffer_start = -1;
        list($startpos, $endpos, $starthead, $endhead, $header) = seek($fp, 0, $fs);
        list($beforerev, $targetrev, $head) = parsehead($fp, $startpos, $endpos, $BEFORE, $TARGET);
        if (!$BEFORE && !$TARGET) $targetrev = $head;
        //print("head = $head\n");
        if ($targetrev && $targetrev != $beforerev)
        {
          $foundrev = false;
          while($endhead < $fs && $header != "desc")
          {
            if ($header == $targetrev)
              $foundrev = true;
            
            list($startpos, $endpos, $starthead, $endhead, $header) = seek($fp, $endhead, $fs);
            
            if ($foundrev)
            {
              fseek($fp, $startpos);
              $str1 = fread($fp, $endpos - $startpos);
              $reg = "/^(?:[^@]*;)?\\s*date\\s+(\\d{4}\\.\\d{2}\\.\\d{2}\\.\\d{2}\\.\\d{2}\\.\\d{2})(?=\\s*;)/s";

              if(!preg_match($reg,$str1,$result))
                print("Date field not found in revision $targetrev\n");
              else
              {
                $olddate = explode(".",$result[1]);
                $olddate = gmmktime($olddate[3], $olddate[4], $olddate[5], $olddate[1], $olddate[2], $olddate[0]);
                
                $olds = date("D M j Y g:i:s A",$olddate);
                $news = date("D M j Y g:i:s A",$date);
                
                if ($date < $olddate || $FORCE)
                {
                  print("Revision $targetrev, changing $olds to $news.\n");
                  fseek($fp,$startpos + strlen($result[0]) - strlen($result[1]));
                  fwrite($fp,gmdate("Y.m.d.H.i.s", $date));
                }
                else
                  print("Revision $targetrev, not changing $olds to $news.\n");
              }
              break;
            }
          }
          fclose($fp);
          if (!$foundrev)
            print("Unable to find revision '$targetrev'\n");
        }
        else if ($targetrev)
          print ("No changes made. ($targetrev == $beforerev)\n");
        else if ($beforerev)  
          print("Target tag not found.\n");
        else
          print("No target tags or anti-target tags found.\n");  
      }
      else
        print("Repository file not found.\n");
    }
  }
}

///////////////////////////////////////////////////////////////////////////////

// Globals

$buffer = "";
$buffer_start = -1;
$buffer_size = 4096;

$WORKING    = "";
$REPOSITORY = "";
$BEFORE     = "";
$TARGET     = "";
$FORCE = false;

// Decipher arguments

$realargs = 0;
$argorder = array("REPOSITORY","WORKING","TARGET","BEFORE");
for($i=1; $i < $argc; ++$i)
{
  $a = $argv[$i];
  if ($a == "--help" || $a == "-h")
  {
    $realargs = 0;
    break;
  }
  else if ($a == "--force" || $a == "-f")
    $FORCE = true;
  else
  {
    $$argorder[$realargs++] = $a;
    }  
}

print("Force:        $FORCE\nRepository:   $REPOSITORY\nWorking:      $WORKING\nTarget:       $TARGET\nBefore:       $BEFORE\n");

if ($realargs < 2)
  showhelp();    
else  
  DoFolder("");

?>