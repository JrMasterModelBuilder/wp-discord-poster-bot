'use strict';

import util from 'util';
import stream from 'stream';

import fse from 'fs-extra';
import del from 'del';
import fastGlob from 'fast-glob';
import {execa} from 'execa';
import gulp from 'gulp';
import gulpInsert from 'gulp-insert';
import archiver from 'archiver';

const pipeline = util.promisify(stream.pipeline);

const buildDir = 'build';
const distDir = 'dist';
const phpWpCheckCode = `defined('ABSPATH') or exit;`;

async function exec(cmd, args = []) {
	await execa(cmd, args, {
		preferLocal: true,
		stdio: 'inherit'
	});
}

// clean

gulp.task('clean', async () => {
	await del([`${buildDir}/*`, distDir], {dot: true});
});

// formatting

gulp.task('format', async () => {
	await exec('prettier', ['-w', '.']);
});

gulp.task('formatted', async () => {
	await exec('prettier', ['-c', '.']);
});

// build

gulp.task('build:index', async () => {
	await Promise.all(
		[
			buildDir,
			...(await fastGlob([`${buildDir}/**/*`], {
				onlyDirectories: true
			}))
		].map(p => fse.outputFile(`${p}/index.html`, '', 'utf8'))
	);
});

gulp.task('build:meta', async () => {
	const pkg = await fse.readJSON('./package.json');
	await fse.outputFile(
		`${buildDir}/main.php`,
		[
			'<?php',
			'/*',
			`Plugin Name: ${pkg.nameDisplay}`,
			`Version: ${pkg.version}`,
			`Author: ${pkg.author}`,
			`License: ${pkg.license}`,
			`Description: ${pkg.description}`,
			'*/',
			'',
			phpWpCheckCode,
			`define('WP_DISCORD_POSTER_BOT_VERSION', '${pkg.version}');`,
			"define('WP_DISCORD_POSTER_BOT_ENTRY', __FILE__);",
			"require_once(__DIR__ . '/lib/main.php');",
			''
		].join('\n'),
		'utf8'
	);
});

gulp.task('watch:meta', () => {
	gulp.watch(['package.json', 'gulpfile.mjs'], gulp.series(['build:meta']));
});

gulp.task('build:lib', async () => {
	await del([`${buildDir}/lib`]);
	await pipeline([
		gulp.src('lib/**/*.php', {base: '.'}),
		gulpInsert.prepend(`<?php ${phpWpCheckCode}?>`),
		gulp.dest(buildDir)
	]);
});

gulp.task('watch:lib', () => {
	gulp.watch(['lib/**/*'], gulp.series(['build:lib', 'build:index']));
});

gulp.task(
	'build',
	gulp.series([gulp.parallel(['build:meta', 'build:lib']), 'build:index'])
);

// dist

gulp.task('dist', async () => {
	const pkg = await fse.readJSON('./package.json');
	const archive = archiver('zip', {
		zlib: {
			level: 9
		}
	});
	archive.on('warning', err => {
		archive.emit('error', err);
	});
	const done = pipeline(
		archive,
		fse.createWriteStream(`${distDir}/${pkg.name}.zip`)
	);
	archive.directory(buildDir, pkg.name);
	archive.finalize();
	await done;
});

// all

gulp.task('all', gulp.series(['clean', 'build', 'formatted', 'dist']));

// watch

gulp.task('watch', gulp.parallel(['watch:meta', 'watch:lib']));

// default

gulp.task('default', gulp.series(['all']));
