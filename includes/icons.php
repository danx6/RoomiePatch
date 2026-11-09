<?php
// Hand-made inline SVG icons. All use a 24x24 grid, 2px stroke and currentColor,
// so they follow the text colour of whatever they sit in.
// Usage: echo icon('sun');  or  echo icon('check', 16);

function icon($name, $size = 20)
{
    $paths = [
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
        'home'     => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'list'     => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'message'  => '<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1z"/>',
        'meetup'   => '<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 20a5.5 5.5 0 0 1 11 0M14 20a4 4 0 0 1 7.5-2"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'flag'     => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11"/>',
        'check'    => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'reply'    => '<path d="M10 8L4 13l6 5"/><path d="M4 13h10a6 6 0 0 1 6 6"/>',
        'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'bell'     => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
        'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    return '<svg class="icon" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $paths[$name] . '</svg>';
}
