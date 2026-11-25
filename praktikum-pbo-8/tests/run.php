<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
$pass=0;
function check(bool $ok, string $label): void { 
        global $pass; if(!$ok) throw new RuntimeException('FAIL: '.$label); $pass++; echo "PASS: $label\n";
        }
function fails(callable $fn, string $exception, string $label): void {
        try{$fn();}catch(Throwable $e){check($e instanceof $exception,$label);return;}throw new RuntimeException('FAIL: '.$label);
        }
$r=new StudentRepository();
$a=new RegularStudent('M01','Alya','Informatika',5000000);
$b=new ScholarshipStudent('M02','Bima','SI',5000000,40);

check($a instanceof Student,'Inheritance regular');
check($b instanceof Student,'Inheritance scholarship');
check($a->calculateFee()===5000000.0,'Overriding biaya reguler');
check($b->calculateFee()===3000000.0,'Overriding biaya beasiswa');
check(str_contains($b->summary(),'[Beasiswa]'),'Overriding + parent::');

$r->create($a);$r->create($b);
check(count($r->all())===2,'Create');
check($r->find('M02')===$b,'Read');
fails(fn()=>$r->create($a),InvalidArgumentException::class,'NIM duplikat ditolak');
$r->update('M01',new RegularStudent('M03','Cici','Informatika',4000000));
check($r->find('M01')===null && $r->find('M03')!==null,'Update NIM');
$r->delete('M03');
check(count($r->all())===1,'Delete');
$p=new StudentPresenter();
check($p->showData('M01','Alya','TI')==='NIM: M01, Nama: Alya, Prodi: TI','__call tiga argumen valid');
fails(fn()=>$p->showData('Alya'),InvalidArgumentException::class,'__call 1 argumen ditolak');
fails(fn()=>$p->showData('M01','Alya'),InvalidArgumentException::class,'__call 2 argumen ditolak');
fails(fn()=>$p->showData('M01','Alya','TI','x'),InvalidArgumentException::class,'__call 4 argumen ditolak');
fails(fn()=>$p->showData('M01',123,'TI'),InvalidArgumentException::class,'__call tipe non-string ditolak');
fails(fn()=>$p->showData('M01','  ','TI'),InvalidArgumentException::class,'__call string kosong ditolak');
fails(fn()=>$p->showData(),InvalidArgumentException::class,'__call tanpa argumen ditolak');
fails(fn()=>$p->unknown(),BadMethodCallException::class,'__call method invalid');
fails(fn()=>new ScholarshipStudent('M04','Dina','TI',1000,101),InvalidArgumentException::class,'Diskon invalid');
check((new ReflectionMethod(Student::class,'getId'))->isFinal(),'Final method tidak boleh override');

// Tugas 3: InternationalStudent, repository tidak perlu tahu class child
$i=new InternationalStudent('M10','Chen','TI',5000000);
check($i instanceof Student,'Inheritance international');
check($i->calculateFee()===7500000.0,'Overriding biaya internasional');
$r2=new StudentRepository();
$r2->create($a);$r2->create($b);$r2->create($i);
check($r2->find('M10')===$i,'Repository menyimpan child baru tanpa perubahan');
$total=array_sum(array_map(fn(Student $s)=>$s->calculateFee(),$r2->all()));
check($total===15500000.0,'Polimorfisme: total biaya 3 tipe');
echo "TOTAL: $pass tests passed\n";
