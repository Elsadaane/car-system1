<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Support\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * المرفقات: بتتخزن على disk "local" (مش public) - يعني مفيش رابط مباشر للملف،
 * والتنزيل بيعدّي على الكنترولر ده بعد التأكد إن اليوزر عامل دخول.
 */
class AttachmentController extends Controller
{
    /** الأنواع المسموح يتعلق عليها مرفقات */
    public const TYPES = [
        'customer' => \App\Models\Customer::class,
        'supplier' => \App\Models\Supplier::class,
        'vehicle'  => \App\Models\Vehicle::class,
    ];

    /** كل نوع مرفقات تابع لأنهي قسم في الصلاحيات */
    private const MODULES = [
        'customer' => 'customers',
        'supplier' => 'suppliers',
        'vehicle'  => 'vehicles',
    ];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $owner = self::TYPES[$type]::findOrFail($id);
        $this->authorizeOwner($type, $owner, 'edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ], [
            'name.required' => 'اكتب اسم المرفق (مثلاً: صورة البطاقة).',
            'file.required' => 'اختار الملف.',
            'file.max'      => 'الملف أكبر من 10 ميجا.',
            'file.mimes'    => 'الملف لازم يكون PDF أو صورة أو Word أو Excel.',
        ]);

        $file = $request->file('file');

        Attachment::create([
            'attachable_type' => $owner->getMorphClass(),
            'attachable_id'   => $owner->getKey(),
            'name'            => $data['name'],
            'path'            => $file->store('attachments/'.$type.'/'.$owner->getKey(), 'local'),
            'original_name'   => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type'       => $file->getMimeType(),
            'size'            => $file->getSize(),
            'user_id'         => $request->user()->id,
        ]);

        return back()->with('status', 'تم رفع المرفق')->withFragment('attachments');
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($attachment, 'view');
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $name = $attachment->name.'.'.pathinfo($attachment->path, PATHINFO_EXTENSION);

        return $request->boolean('inline') && ($attachment->isImage() || $attachment->mime_type === 'application/pdf')
            ? Storage::disk('local')->response($attachment->path, $name)
            : Storage::disk('local')->download($attachment->path, $name);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || (int) $attachment->user_id === (int) $request->user()->id, 403);
        $this->authorizeAttachment($attachment, 'edit');

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', 'تم مسح المرفق')->withFragment('attachments');
    }

    /**
     * المرفقات فيها بطايق وعقود - مينفعش أي موظف يفتحها:
     * لازم يكون عنده صلاحية القسم (عملاء/موردين/مركبات)، ولو مقفول على فرع يبقى السجل من فرعه.
     */
    private function authorizeAttachment(Attachment $attachment, string $action): void
    {
        $type = array_search($attachment->attachable_type, array_map(fn ($c) => (new $c)->getMorphClass(), self::TYPES), true);
        abort_unless($type !== false, 404);

        $owner = self::TYPES[$type]::withTrashed()->find($attachment->attachable_id);
        abort_unless($owner, 404);

        $this->authorizeOwner($type, $owner, $action);
    }

    private function authorizeOwner(string $type, Model $owner, string $action): void
    {
        abort_unless(Access::can(self::MODULES[$type].'.'.$action), 403, 'مش مسموحلك تتعامل مع المرفقات دي.');

        $ids = Access::branchIds();
        $branch = $owner->getAttribute('branch_id');
        if ($ids !== null && $branch && ! in_array((int) $branch, $ids, true)) {
            abort(403, 'السجل ده تابع لفرع تاني.');
        }
    }
}
