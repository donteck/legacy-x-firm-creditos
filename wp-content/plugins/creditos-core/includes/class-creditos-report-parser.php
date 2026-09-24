<?php
if (!defined('ABSPATH')) exit;

class CreditOS_Report_Parser {
    public function parse($path, $format, $bureau = 'multi') {
        if (!$path || !file_exists($path)) return new WP_Error('creditos_parser_file_missing', 'The uploaded credit report file could not be found.');
        $format = strtolower((string) $format);
        if ($format === 'pdf') return $this->parse_pdf($path, $bureau);
        if ($format === 'csv') return $this->parse_csv($path, $bureau);
        return new WP_Error('creditos_parser_format', 'Unsupported parser format.');
    }

    private function parse_pdf($path, $bureau) {
        $bin = $this->find_pdftotext();
        $text = '';
        $method = 'none';
        $attempts = array();

        // Prefer pdftotext when PHP is allowed to launch a process.
        if ($bin && $this->can_launch_process()) {
            $text = $this->run_pdftotext($bin, $path, '-layout');
            $method = 'pdftotext-layout';
            $attempts[] = array('method'=>$method,'text_length'=>is_string($text)?strlen($text):0,'usable'=>$this->looks_like_credit_report($text));
            if (!$text || !$this->looks_like_credit_report($text)) {
                $text = $this->run_pdftotext($bin, $path, '-raw');
                $method = 'pdftotext-raw';
                $attempts[] = array('method'=>$method,'text_length'=>is_string($text)?strlen($text):0,'usable'=>$this->looks_like_credit_report($text));
            }
        }

        // Use the bundled Composer PDF engine when shell execution is unavailable.
        if (!$text || !$this->looks_like_credit_report($text)) {
            $vendor = dirname(__DIR__) . '/vendor/autoload.php';
            if (file_exists($vendor)) {
                require_once $vendor;
                if (class_exists('Smalot\\PdfParser\\Parser')) {
                    try {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($path);
                        $candidate = $pdf ? $pdf->getText() : '';
                        if (is_string($candidate) && $candidate !== '') {
                            $text = $this->clean_text($candidate);
                            $method = 'smalot-pdfparser';
                            $attempts[] = array('method'=>$method,'text_length'=>strlen($text),'usable'=>$this->looks_like_credit_report($text));
                        }
                    } catch (\Throwable $e) {
                        // Continue to the conservative internal fallback.
                    }
                }
            }
        }

        // Last local fallback for simple text-based PDFs.
        if (!$text || !$this->looks_like_credit_report($text)) {
            $text = $this->extract_pdf_text_php($path);
            $method = 'php-stream';
            $attempts[] = array('method'=>$method,'text_length'=>is_string($text)?strlen($text):0,'usable'=>$this->looks_like_credit_report($text));
        }

        // Allow a future audited extractor/service to plug in without changing
        // normalization code. Never expose the extracted report text in diagnostics.
        if ((!$text || !$this->looks_like_credit_report($text)) && function_exists('apply_filters')) {
            $filtered = apply_filters('creditos_pdf_extract_text', '', $path, $bureau);
            if (is_string($filtered) && $filtered !== '') {
                $text = $this->clean_text($filtered);
                $method = 'provider-filter';
            }
        }

        if (!$text || !$this->looks_like_credit_report($text)) {
            $summary = array_map(function($a){ return $a['method'].':'.$a['text_length'].':'.($a['usable']?'usable':'unusable'); }, $attempts);
            return new WP_Error('creditos_pdf_needs_ocr', 'CreditOS could not recover enough structured text from this PDF. Safe extraction diagnostics: '.implode(', ', $summary));
        }
        $detected = $this->detect_bureau($text, $bureau);
        $normalized = $this->normalize_text($text, $detected);
        return array('status'=>'normalized','bureau'=>$detected,'text_length'=>strlen($text),'extraction_method'=>$method,'account_block_count'=>count($this->account_blocks($text)),'normalized'=>$normalized);
    }

    private function normalize_text($text, $bureau) {
        if ($bureau === 'experian' || stripos($text, 'Experian') !== false) return $this->normalize_experian($text);
        return array('tradelines'=>array(),'collections'=>array(),'inquiries'=>array(),'personal_information'=>$this->parse_basic_personal($text,$bureau));
    }

    private function normalize_experian($text) {
        $tradelines = array();
        foreach ($this->account_blocks($text) as $block) {
            $name = $this->line_value($block, 'Account Name');
            $number = $this->line_value($block, 'Account Number');
            if (!$name || (!$number && stripos($block, 'Account Type') === false)) continue;
            $status = $this->multiline_value($block, 'Status', array('Status Updated','Balance','Balance Updated'), 3);
            $tradelines[] = array(
                'creditor_name'=>$name,'bureau'=>'experian','account_number_masked'=>$number,
                'account_type'=>$this->line_value($block,'Account Type'),'responsibility'=>$this->line_value($block,'Responsibility'),
                'opened_date'=>$this->line_value($block,'Date Opened'),'status'=>$status,
                'balance'=>$this->number($this->line_value($block,'Balance')),'credit_limit'=>$this->number($this->line_value($block,'Credit Limit')),
                'past_due'=>$this->past_due($status),'payment_status'=>$this->payment_status($block,$status),
                'date_reported'=>$this->line_value($block,'Balance Updated'),'remarks'=>$this->remarks($block)
            );
        }
        $collections = array();
        $seen_collections = array();
        foreach ($this->account_blocks($text) as $block) {
            $name = $this->line_value($block, 'Account Name');
            $type = $this->line_value($block, 'Account Type');
            $status = $this->multiline_value($block, 'Status', array('Status Updated','Balance','Balance Updated'), 3);
            $is_collection = stripos($block, 'Collection account') !== false || stripos($type, 'collection') !== false || stripos($type, 'debt buyer') !== false;
            if (!$name || !$is_collection) continue;
            $key = strtolower(trim($name . '|' . $this->line_value($block, 'Account Number')));
            if (isset($seen_collections[$key])) continue;
            $seen_collections[$key] = true;
            $collections[] = array(
                'collector_name'=>$name,
                'original_creditor'=>'',
                'balance'=>$this->number($this->line_value($block,'Balance')),
                'assigned_date'=>null,
                'status'=>$status,
                'bureau'=>'experian'
            );
        }
        return array('tradelines'=>$tradelines,'collections'=>$collections,'inquiries'=>$this->parse_hard_inquiries($text),'personal_information'=>$this->parse_basic_personal($text,'experian'));
    }

    private function account_blocks($text) {
        $blocks=array();
        if (!preg_match_all('/^[ \t]*Account\s+Name[ \t]+.+$/mi',$text,$matches,PREG_OFFSET_CAPTURE)) return $blocks;
        $hits=$matches[0];
        for($i=0,$n=count($hits);$i<$n;$i++){
            $start=$hits[$i][1]; $end=($i+1<$n)?$hits[$i+1][1]:strlen($text); $block=substr($text,$start,$end-$start);
            if(stripos($block,'Account Number')!==false && stripos($block,'Account Type')!==false) $blocks[]=$block;
        }
        return $blocks;
    }

    private function line_value($block,$label) {
        $pattern='/^[ \t]*'.preg_quote($label,'/').'[ \t]*(?:[:\-][ \t]*)?(.+?)[ \t]*$/mi';
        if(!preg_match($pattern,$block,$m)) return '';
        $value=trim(preg_replace('/[ \t]+/',' ',$m[1]));
        return $value==='-'?'':$value;
    }

    private function multiline_value($block,$label,$stop_labels,$max_lines=3) {
        $lines=preg_split('/\n/',$block); $out=array(); $capture=false;
        foreach($lines as $line){
            if(!$capture){if(preg_match('/^[ \t]*'.preg_quote($label,'/').'[ \t]+(.+)$/i',$line,$m)){$out[]=trim($m[1]);$capture=true;}continue;}
            foreach($stop_labels as $stop) if(preg_match('/^[ \t]*'.preg_quote($stop,'/').'\b/i',$line)) return trim(preg_replace('/\s+/',' ',implode(' ',$out)));
            $part=trim($line); if($part!=='')$out[]=$part; if(count($out)>=$max_lines)break;
        }
        return trim(preg_replace('/\s+/',' ',implode(' ',$out)));
    }

    private function past_due($status){return preg_match('/\$([0-9,]+(?:\.\d{2})?)\s+past due/i',(string)$status,$m)?$this->number($m[1]):null;}
    private function payment_status($block,$status){if(stripos($status,'charged off')!==false)return'Charge Off';if(preg_match('/\b(30|60|90|120|150|180) days past due\b/i',$block,$m))return$m[1].' Days Past Due';if(stripos($status,'paid')!==false&&stripos($status,'closed')!==false)return'Paid / Closed';if(stripos($status,'open')!==false)return'Open';return$status;}
    private function remarks($block){$r=array();$until=$this->line_value($block,'On Record Until');if($until)$r[]='On Record Until '.$until;return implode('; ',array_unique($r));}
    private function parse_basic_personal($text,$bureau){$out=array();if(preg_match('/Prepared For\s*[:\-]?\s*\n?\s*([^\n]{3,80})/i',$text,$m))$out[]=array('info_type'=>'name','info_value'=>trim($m[1]),'bureau'=>$bureau);if(preg_match('/Date Generated\s*[:\-]?\s*([A-Za-z]{3,9}\s+\d{1,2},\s+\d{4})/i',$text,$m))$out[]=array('info_type'=>'report_date','info_value'=>trim($m[1]),'bureau'=>$bureau);return$out;}
    private function parse_hard_inquiries($text){if(preg_match('/\b0\s+Hard Inquiries\b/i',$text))return array();return array();}
    private function looks_like_credit_report($text){if(strlen((string)$text)<120)return false;$hits=0;foreach(array('Account Name','Account Number','Experian','Equifax','TransUnion','Credit Report','Prepared For')as$needle)if(stripos($text,$needle)!==false)$hits++;return$hits>=2;}
    private function detect_bureau($text,$fallback){if(stripos($text,'Annual Credit Report - Experian')!==false||stripos($text,'usa.experian.com/acr/')!==false||(stripos($text,'Prepared For')!==false&&stripos($text,'Experian')!==false))return'experian';if(stripos($text,'Equifax')!==false&&stripos($text,'Experian')===false)return'equifax';if(stripos($text,'TransUnion')!==false&&stripos($text,'Experian')===false)return'transunion';return in_array($fallback,array('experian','equifax','transunion','multi'),true)?$fallback:'multi';}

    private function function_enabled($name){
        if(!function_exists($name)) return false;
        $disabled=array_map('trim',explode(',',(string)ini_get('disable_functions')));
        return !in_array($name,$disabled,true);
    }

    private function can_launch_process(){
        return $this->function_enabled('proc_open') || $this->function_enabled('exec') || $this->function_enabled('shell_exec');
    }

    /**
     * Conservative PHP-only PDF text fallback for text-based PDFs.
     * It decodes unencrypted Flate streams and extracts literal/hex strings used
     * by common PDF text operators. It is intentionally not an OCR engine.
     */
    private function extract_pdf_text_php($path){
        $pdf=@file_get_contents($path);
        if(!is_string($pdf)||$pdf==='') return '';
        // Do not attempt to bypass encrypted/password-protected reports.
        if(preg_match('/\/Encrypt\b/',$pdf)) return '';

        $chunks=array();
        if(preg_match_all('/stream\R(.*?)\Rendstream/s',$pdf,$streams)){
            foreach($streams[1] as$stream){
                $decoded=$stream;
                $try=@gzuncompress($stream);
                if($try===false) $try=@gzinflate($stream);
                if($try===false && strlen($stream)>2) $try=@gzinflate(substr($stream,2));
                if(is_string($try)&&$try!=='') $decoded=$try;
                $text=$this->pdf_text_operators($decoded);
                if($text!=='') $chunks[]=$text;
            }
        }
        // Some simple PDFs keep text operators outside compressed streams.
        $outer=$this->pdf_text_operators($pdf);
        if($outer!=='') $chunks[]=$outer;
        return $this->clean_text(implode("\n",$chunks));
    }

    private function pdf_text_operators($data){
        if(!is_string($data)||$data==='') return '';
        $out=array();
        // Literal strings followed by Tj, quote operators, or inside TJ arrays.
        if(preg_match_all('/\((?:\\.|[^\\)])*\)/s',$data,$m)){
            foreach($m[0] as$token){
                $v=substr($token,1,-1);
                $v=preg_replace_callback('/\\\\([0-7]{1,3})/',function($x){return chr(octdec($x[1]));},$v);
                $v=str_replace(array('\\n','\\r','\\t','\\b','\\f','\\(','\\)','\\\\'),array("\n","\r","\t","\b","\f",'(',')','\\'),$v);
                if($this->printable_ratio($v)>=0.75) $out[]=$v;
            }
        }
        // Hex strings are common in generated reports. Decode only readable text;
        // font-specific glyph maps remain the job of a dedicated extractor.
        if(preg_match_all('/<([0-9A-Fa-f]{4,})>/',$data,$hex)){
            foreach($hex[1] as$h){
                if(strlen($h)%2) $h.='0';
                $v=@hex2bin($h);
                if(is_string($v)&&$this->printable_ratio($v)>=0.80) $out[]=$v;
            }
        }
        return implode("\n",$out);
    }

    private function printable_ratio($value){
        $len=strlen((string)$value);
        if(!$len) return 0;
        $print=preg_match_all('/[\x09\x0A\x0D\x20-\x7E]/',(string)$value,$m);
        return $print/$len;
    }

    private function temp_text_file(){
        $dir = function_exists('wp_upload_dir') ? wp_upload_dir() : array();
        $base = !empty($dir['basedir']) ? $dir['basedir'] : sys_get_temp_dir();
        $folder = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'creditos-tmp';
        if(!is_dir($folder)) @wp_mkdir_p($folder);
        if(!is_dir($folder) || !is_writable($folder)) $folder = sys_get_temp_dir();
        $tmp = @tempnam($folder, 'creditos_');
        return $tmp ? $tmp : '';
    }

    private function run_pdftotext($bin,$path,$mode){
        $tmp=$this->temp_text_file(); if(!$tmp)return'';
        $cmd=escapeshellarg($bin).' '.escapeshellarg($mode).' -enc UTF-8 '.escapeshellarg($path).' '.escapeshellarg($tmp);
        $ok=false;
        if($this->function_enabled('proc_open')){
            $spec=array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'));
            $pipes=array(); $proc=@proc_open($cmd,$spec,$pipes);
            if(is_resource($proc)){
                @fclose($pipes[0]); if(isset($pipes[1])){@stream_get_contents($pipes[1]);@fclose($pipes[1]);} if(isset($pipes[2])){@stream_get_contents($pipes[2]);@fclose($pipes[2]);}
                $ok=(@proc_close($proc)===0);
            }
        } elseif($this->function_enabled('exec')) {
            $output=array();$code=1;@exec($cmd.' 2>&1',$output,$code);$ok=($code===0);
        } elseif($this->function_enabled('shell_exec')) {
            @shell_exec($cmd.' 2>&1');$ok=file_exists($tmp)&&filesize($tmp)>0;
        }
        $text=($ok&&file_exists($tmp))?@file_get_contents($tmp):''; @unlink($tmp); return$this->clean_text($text);
    }

    private function clean_text($text){$text=str_replace("\0",'',(string)$text);$text=preg_replace("/\r\n?|\r/","\n",$text);$text=preg_replace('/[ \t]+$/m','',$text);return trim($text);}
    private function parse_csv($path,$bureau){$h=fopen($path,'r');if(!$h)return new WP_Error('creditos_csv_open','Could not read CSV.');$head=fgetcsv($h);if(!$head){fclose($h);return new WP_Error('creditos_csv_header','Unreadable CSV header.');}$head=array_map(function($v){return sanitize_key(str_replace(array(' ','-'),'_',strtolower(trim($v))));},$head);$rows=array();while(($x=fgetcsv($h))!==false){$x=array_pad($x,count($head),'');$r=array_combine($head,array_slice($x,0,count($head)));$name=isset($r['creditor_name'])?$r['creditor_name']:(isset($r['creditor'])?$r['creditor']:(isset($r['account_name'])?$r['account_name']:''));if($name)$rows[]=array('creditor_name'=>$name,'bureau'=>isset($r['bureau'])?$r['bureau']:$bureau,'account_number_masked'=>isset($r['account_number'])?$r['account_number']:'','account_type'=>isset($r['account_type'])?$r['account_type']:'','balance'=>$this->number(isset($r['balance'])?$r['balance']:null),'credit_limit'=>$this->number(isset($r['credit_limit'])?$r['credit_limit']:null),'status'=>isset($r['status'])?$r['status']:'');}fclose($h);return array('status'=>'normalized','bureau'=>$bureau,'normalized'=>array('tradelines'=>$rows,'collections'=>array(),'inquiries'=>array(),'personal_information'=>array()));}
    private function find_pdftotext(){foreach(array('/usr/bin/pdftotext','/usr/local/bin/pdftotext')as$path)if(is_executable($path))return$path;return false;}
    private function number($value){if($value===null||trim((string)$value)===''||trim((string)$value)==='-')return null;$clean=preg_replace('/[^0-9.\-]/','',(string)$value);return is_numeric($clean)?(float)$clean:null;}
    public function safe_excerpt($text,$limit=800){$text=preg_replace('/\b\d{3}-\d{2}-\d{4}\b/','***-**-****',(string)$text);$text=preg_replace('/\b\d{9,16}\b/','********',$text);return function_exists('mb_substr')?mb_substr($text,0,$limit):substr($text,0,$limit);}
}
