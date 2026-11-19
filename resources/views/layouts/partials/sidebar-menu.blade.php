{{--
    Menú lateral único (escritorio y celular). Definido en config/menu.php.
    Cada opción lleva el color de su módulo: el ícono en un cuadrito de color y,
    si está activa, fondo suave y borde izquierdo del mismo color.
--}}
@foreach(\App\Support\Menu::sections(auth()->user(), $currentCompany) as $section)
    <div class="sidebar-section-title">{{ $section['title'] }}</div>
    <ul class="nav flex-column sidebar-nav">
        @foreach($section['items'] as $item)
            <li class="nav-item">
                <a class="sidebar-link mod-{{ $item['module'] }} {{ $item['is_active'] ? 'active' : '' }}"
                   href="{{ route($item['route']) }}"
                   @if(!empty($item['external'])) target="_blank" rel="noopener" title="Se abre en una pestaña nueva" @endif
                   @if($item['is_active']) aria-current="page" @endif>
                    <span class="sidebar-link-icon"><i class="bi bi-{{ $item['icon'] }}"></i></span>
                    <span class="sidebar-link-label">{{ $item['label'] }}</span>
                    @if(!empty($item['external']))<i class="bi bi-box-arrow-up-right sidebar-link-ext"></i>@endif
                </a>
            </li>
        @endforeach
    </ul>
@endforeach
