<?php
declare(strict_types=1);
namespace App\Controllers\Staff;
use App\Controllers\BaseController;
use App\Services\AuditLogger;
use App\Services\DocumentStorage;
final class Documents extends BaseController
{
    public function photo(int $childId)
    {
        $child=db_connect()->table('children')->select('photo_path')->where('id',$childId)->where('deleted_at',null)->get()->getRowArray();if(!$child||!$child['photo_path'])throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $path=(new DocumentStorage())->path($childId,$child['photo_path']);$extension=pathinfo($path,PATHINFO_EXTENSION);return $this->response->setHeader('Content-Type',$extension==='png'?'image/png':'image/jpeg')->setHeader('Content-Disposition','inline')->setHeader('X-Content-Type-Options','nosniff')->setBody(file_get_contents($path));
    }
    public function show(int $childId,int $documentId)
    {
        $doc=db_connect()->table('child_documents')->where(['id'=>$documentId,'child_id'=>$childId])->where('deleted_at',null)->get()->getRowArray();
        if(!$doc)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        $path=(new DocumentStorage())->path($childId,$doc['stored_name']);
        if($this->request->getGet('download')==='1'){
            (new AuditLogger())->log('download_document','child_document',$documentId);
            return $this->response->download($path,null)->setFileName(basename($doc['original_name']));
        }
        (new AuditLogger())->log('view_document','child_document',$documentId);
        return $this->response->setHeader('Content-Type',$doc['mime_type'])->setHeader('Content-Disposition','inline; filename="'.str_replace(['"',"\\",'\r','\n'],'_',basename($doc['original_name'])).'"')->setHeader('X-Content-Type-Options','nosniff')->setBody(file_get_contents($path));
    }
    public function verify(int $childId,int $documentId){return $this->setStatus($childId,$documentId,'verified');}
    public function reject(int $childId,int $documentId){return $this->setStatus($childId,$documentId,'rejected');}
    private function setStatus(int $childId,int $documentId,string $status)
    {
        $reason=trim((string)$this->request->getPost('reason'));if($status==='rejected'&&$reason==='')return redirect()->back()->with('error','Enter a reason when rejecting a document.');
        db_connect()->table('child_documents')->where(['id'=>$documentId,'child_id'=>$childId])->update(['status'=>$status,'verified_by'=>auth()->id(),'verified_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        (new AuditLogger())->log('document_'.$status,'child_document',$documentId,null,['child_id'=>$childId,'status'=>$status,'reason'=>$reason]);
        return redirect()->to(site_url('children/'.$childId))->with('message','Document status updated.');
    }
}
