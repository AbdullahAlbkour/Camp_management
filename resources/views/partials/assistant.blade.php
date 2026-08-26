{{--
    Floating assistant.

    Rendered once per authenticated page from the layout. The markup is inert
    until app.js wires it, and the panel stays in the DOM so the conversation
    survives opening and closing without a round trip.
--}}
<div class="assistant" data-assistant="{{ route('assistant.ask') }}">
    <button class="assistant-launcher" type="button" data-assistant-toggle
            aria-expanded="false" aria-controls="assistantPanel" title="المساعد الذكي (Ctrl + K)">
        <i data-lucide="sparkles"></i>
        <span class="assistant-launcher-label">المساعد</span>
    </button>

    <section class="assistant-panel" id="assistantPanel" role="dialog" aria-modal="false"
             aria-label="المساعد الذكي" hidden>
        <header class="assistant-head">
            <span class="assistant-avatar"><i data-lucide="sparkles"></i></span>
            <div class="assistant-title">
                <strong>المساعد الذكي</strong>
                <small>يجيب من بيانات النظام مباشرة</small>
            </div>
            <button class="assistant-close" type="button" data-assistant-close title="إغلاق" aria-label="إغلاق المساعد">
                <i data-lucide="x"></i>
            </button>
        </header>

        {{-- aria-live so a screen reader announces each answer as it lands. --}}
        <div class="assistant-log" data-assistant-log role="log" aria-live="polite" aria-atomic="false">
            <div class="assistant-msg is-bot">
                <p>مرحبًا {{ auth()->user()->name }}. اسألني عن السكان، الأسر، الوحدات السكنية أو المساعدات — أجيب من قاعدة البيانات مباشرة.</p>
            </div>
            <div class="assistant-chips" data-assistant-suggestions>
                @foreach ($assistantSuggestions ?? [] as $suggestion)
                    <button type="button" data-assistant-example="{{ $suggestion }}">{{ $suggestion }}</button>
                @endforeach
            </div>
        </div>

        <form class="assistant-form" data-assistant-form>
            <label class="sr-only" for="assistantQuestion">اكتب سؤالك</label>
            <textarea id="assistantQuestion" name="question" rows="1" maxlength="300" autocomplete="off"
                      placeholder="مثال: {{ $assistantSuggestions[0] ?? 'كم عدد السكان المسجلين؟' }}" data-assistant-input></textarea>
            <button type="submit" title="إرسال" aria-label="إرسال السؤال" data-assistant-send>
                <i data-lucide="send-horizontal"></i>
            </button>
        </form>
    </section>
</div>
