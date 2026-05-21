@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === config('app.name') || trim($slot) === 'Laravel')
                <img src="{{ rtrim(config('app.url'), '/') }}/imagens/yudo.png" class="logo" alt="Logo" style="height: auto; max-height: 50px; width: auto;">
            @else
                {!! $slot !!}
            @endif
        </a>
    </td>
</tr>
