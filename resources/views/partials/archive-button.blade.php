{{--
    The delete button used on every index screen.

    Expects: $resource (archive key), $id, $label (what the confirm names).

    Permission is asked of ArchivableResources rather than written as a @role
    list here, so the button appears exactly where the route would allow it.
--}}
@if (\App\Support\ArchivableResources::allows(auth()->user(), $resource))
    @php
        $archiveConfirm = 'سيُنقل «'.$label.'» إلى الأرشيف ويمكن استرجاعه لاحقًا.'."\n\n".'هل تريد المتابعة؟';
    @endphp
    {{-- Js::from and not interpolation: {{ }} escapes for HTML and the attribute
         is decoded again before the JS parses it, so a quote in a record's name
         would close the string literal and leave the button dead. --}}
    <form method="post" action="{{ route('archive.store', [$resource, $id]) }}"
          onsubmit="return confirm({{ Js::from($archiveConfirm, JSON_UNESCAPED_UNICODE) }});">
        @csrf
        @method('DELETE')
        <button class="icon-link danger-link" type="submit" title="حذف"><i data-lucide="trash-2"></i></button>
    </form>
@endif
