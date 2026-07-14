<?php
namespace App\Services;
use Illuminate\Support\Collection;
class CvTemplateCatalog {
 private const STYLES=['classic'=>'Klassisch','modern'=>'Modern','sidebar'=>'Seitenleiste','minimal'=>'Minimal','executive'=>'Executive','creative'=>'Kreativ'];
 private const COLORS=['navy'=>['Marine','#16324f'],'blue'=>['Blau','#1d4ed8'],'teal'=>['Petrol','#0f766e'],'green'=>['Grün','#3f6212'],'burgundy'=>['Bordeaux','#881337'],'violet'=>['Violett','#6d28d9'],'slate'=>['Schiefer','#334155'],'graphite'=>['Graphit','#27272a'],'copper'=>['Kupfer','#9a3412'],'rose'=>['Rosé','#be185d']];
 private const FONTS=['sans'=>['Sans','DejaVu Sans'],'serif'=>['Serif','DejaVu Serif'],'modern'=>['Modern','Arial'],'editorial'=>['Editorial','Georgia']];
 public function all():Collection {$templates=[];foreach(self::STYLES as $style=>$styleName)foreach(self::COLORS as $colorKey=>$color)foreach(self::FONTS as $fontKey=>$font)$templates[]=['key'=>"$style-$colorKey-$fontKey",'name'=>"$styleName · {$color[0]} · {$font[0]}",'style'=>$style,'color'=>$color[1],'font'=>$font[1]];return collect($templates);}
 public function find(?string $key):array{return $this->all()->firstWhere('key',$key)??$this->all()->first();}
 public function keys():array{return $this->all()->pluck('key')->all();}
}
