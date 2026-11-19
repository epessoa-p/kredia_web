{{-- Estilos del contenido del manual: los usan la página y el PDF (dompdf no admite flex/grid). --}}
<style>
    .manual h2 { font-size: 1.45rem; font-weight: 700; color: #0f3d68; margin: 0 0 .35rem; }
    .manual .cap-intro { color: #4a5b6d; margin-bottom: 1rem; }
    .manual h3 { font-size: 1.08rem; font-weight: 700; color: #1f2d3d; margin: 1.3rem 0 .45rem; }
    .manual h4 { font-size: .95rem; font-weight: 700; color: #1f2d3d; margin: .9rem 0 .35rem; }
    .manual p, .manual li { line-height: 1.55; }
    .manual ol.pasos { padding-left: 1.4rem; margin-bottom: .75rem; }
    .manual ol.pasos > li { margin-bottom: .3rem; }
    .manual ul { padding-left: 1.3rem; }
    .manual .boton {
        display: inline-block; padding: 0 .4rem; border: 1px solid #b9c7d6; border-radius: 4px;
        background: #f2f6fa; font-weight: 600; font-size: .92em; color: #0f3d68; white-space: nowrap;
    }
    .manual .menu { font-weight: 700; color: #0f3d68; }
    .manual .nota, .manual .aviso, .manual .consejo {
        border-left: 4px solid; border-radius: 4px; padding: .55rem .8rem; margin: .7rem 0; font-size: .95em;
    }
    .manual .nota { background: #eef5fc; border-color: #3b82c4; }
    .manual .aviso { background: #fff4e5; border-color: #e08a00; }
    .manual .consejo { background: #ecf8f1; border-color: #2a9d63; }
    .manual .nota strong:first-child, .manual .aviso strong:first-child, .manual .consejo strong:first-child { margin-right: .25rem; }
    .manual table.tabla { width: 100%; border-collapse: collapse; margin: .6rem 0 1rem; font-size: .93em; }
    .manual table.tabla th, .manual table.tabla td { border: 1px solid #d5dee8; padding: .35rem .5rem; vertical-align: top; text-align: left; }
    .manual table.tabla th { background: #eef3f8; color: #1f2d3d; }
    .manual .etiqueta { display: inline-block; padding: 0 .45rem; border-radius: 10px; font-size: .85em; font-weight: 700; }
</style>
