<?php
declare(strict_types=1);

function ai_builder_text(array $response): string {
    $text='';
    foreach($response['output']??[] as $output)foreach($output['content']??[] as $content)if(($content['type']??'')==='output_text')$text.=(string)($content['text']??'');
    return trim($text);
}
function ai_builder_generate(string $kind,string $brief,array $catalog=[]):array{
    $key=decrypt_secret(app_setting('openai_api_key_enc'));
    if($key==='')throw new RuntimeException('Configure the OpenAI API key in Admin > AI setup & knowledge base.');
    $brand=['application_name'=>app_setting('app_name','OpenCRM'),'primary_color'=>app_setting('primary_color','#1565c0'),'accent_color'=>app_setting('accent_color','#43a047'),'logo_available'=>(bool)app_setting('logo_path')];
    $instructions='You create editable CRM marketing drafts. Return one JSON object only, without markdown fences. Never include scripts, event handlers, iframes, remote resources, tracking code, or invented claims. Follow the requested schema exactly.';
    $input="Artifact: {$kind}\nBrand: ".json_encode($brand)."\nBusiness knowledge:\n".mb_substr(function_exists('lm_context')?lm_context():'',0,90000)."\nAvailable catalog:\n".json_encode($catalog)."\nUser brief:\n".$brief;
    $payload=json_encode(['model'=>app_setting('openai_model','gpt-5.4-mini'),'instructions'=>$instructions,'input'=>$input],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>180,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);$response=json_decode((string)$raw,true);
    if($status<200||$status>=300)throw new RuntimeException('OpenAI request failed: '.mb_strimwidth((string)($response['error']['message']??$err),0,260,'â€¦'));
    $text=ai_builder_text(is_array($response)?$response:[]);$text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$text);
    $data=json_decode(trim((string)$text),true);if(!is_array($data))throw new RuntimeException('The AI response was not valid structured content. Please try again.');return $data;
}
function ai_builder_slug(string $value,string $fallback='draft'):string{$slug=trim(strtolower((string)preg_replace('/[^a-z0-9_-]+/i','-',$value)),'-');return mb_substr($slug?:$fallback,0,100);}
function ai_builder_html(string $html):string{
    $html=preg_replace('#<(script|iframe|object|embed|form|style)\b[^>]*>.*?</\1>#is','',$html);
    $html=preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html);
    $html=preg_replace('/\s+(src|href)\s*=\s*(["\'])\s*(?:javascript:|data:text\/html)[^"\']*\2/i','',$html);
    return mb_substr(strip_tags($html,'<header><nav><main><section><article><aside><footer><div><span><h1><h2><h3><h4><p><ul><ol><li><strong><em><small><a><button><img><hr><br>'),0,180000);
}
function ai_builder_css(string $css):string{$css=preg_replace('/@import[^;]*;|url\s*\([^)]*\)|expression\s*\([^)]*\)|<\/style/iu','',$css);return mb_substr($css,0,80000);}

