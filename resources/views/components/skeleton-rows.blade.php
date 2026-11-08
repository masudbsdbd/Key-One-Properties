@props(['cols' => 4, 'rows' => 5])
@for ($r = 0; $r < $rows; $r++)
    <tr aria-hidden="true">
        @for ($c = 0; $c < $cols; $c++)
            <td><div class="skeleton h-4 {{ $c === 0 ? 'w-40' : 'w-24' }}"></div></td>
        @endfor
    </tr>
@endfor
