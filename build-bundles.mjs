#!/usr/bin/env node

import * as esbuild from 'esbuild'

await esbuild.build({
    entryPoints: ['templates/bootstrap/js/dependencies.js'],
    bundle: true,
    minify: true,
    outfile: 'templates/bootstrap/js/dependencies.bundle.js',
    format: 'iife',
})

await esbuild.build({
    entryPoints: ['templates/bootstrap/css/dependencies.css'],
    bundle: true,
    minify: true,
    outfile: 'templates/bootstrap/css/dependencies.bundle.css',
    loader: {'.png': 'dataurl', '.gif': 'dataurl'},
})

await esbuild.build({
    entryPoints: ['opc/js/dependencies.js'],
    bundle: true,
    minify: true,
    outfile: 'opc/js/dependencies.bundle.js',
    format: 'iife',
})

await esbuild.build({
    entryPoints: ['opc/css/dependencies.css'],
    bundle: true,
    minify: true,
    outfile: 'opc/css/dependencies.bundle.css',
    loader: {'.png': 'dataurl', '.gif': 'dataurl', '.eot': 'empty', '.woff': 'empty', '.svg': 'empty', '.woff2': 'file', '.ttf': 'empty'},
    assetNames: '[name]',
})