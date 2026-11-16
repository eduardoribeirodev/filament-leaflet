import fs from 'fs';
import path from 'path';
import type { Plugin } from 'vite';
import centroid from '@turf/centroid';
import booleanPointInPolygon from '@turf/boolean-point-in-polygon';
import { buildLocationIndex, countryDefinitions, generateCountryClasses, validateRegionTranslations } from '../scripts/build-location-index.mjs';
import { simplifyGeometryDirectory } from '../scripts/simplify-geometry.mjs';

export interface SplitOptions {
    countries: string[];
    outDir: string;
    cacheDir?: string;
}

export function geojsonSplitPlugin({ countries, outDir, cacheDir = '.geojson.cache' }: SplitOptions): Plugin {
    return {
        name: 'geojson-split',
        async closeBundle() {
            const definitions = countryDefinitions();
            fs.mkdirSync(cacheDir, { recursive: true });
            fs.mkdirSync(outDir, { recursive: true });

            const manifest: Record<string, { states: string[] }> = {};

            const fetchAndCache = async (url: string, cachePath: string): Promise<string> => {
                if (fs.existsSync(cachePath)) {
                    return fs.readFileSync(cachePath, 'utf8');
                }
                console.log(`split-geojson: Baixando de ${url}...`);

                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`Erro ao baixar ${url}: ${response.statusText}`);
                }

                const data = await response.text();
                fs.writeFileSync(cachePath, data);
                return data;
            };

            const getDownloadUrl = async (countryCode: string, admLevel: string): Promise<string | null> => {
                const metaCachePath = path.join(cacheDir, `${countryCode}-${admLevel}-meta.json`);
                const apiUrl = `https://www.geoboundaries.org/api/current/gbOpen/${countryCode}/${admLevel}/`;

                let metaText: string;
                try {
                    metaText = await fetchAndCache(apiUrl, metaCachePath);
                } catch (err) {
                    console.warn(`split-geojson: Não foi possível obter metadados para ${countryCode} no nível ${admLevel}.`);
                    return null;
                }

                try {
                    const meta = JSON.parse(metaText);
                    const resolvedMeta = Array.isArray(meta) ? meta[0] : meta;

                    return resolvedMeta?.simplifiedGeometryGeoJSON || resolvedMeta?.gjDownloadURL || null;
                } catch (err) {
                    console.warn(`split-geojson: Falha ao parsear os metadados para ${countryCode} ${admLevel}.`);
                    return null;
                }
            };

            const cleanStateCode = (stateFeature: any, countryCode: string): string => {
                const props = stateFeature.properties || {};
                const shapeIso = props.shapeISO;
                const normalize = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
                const definition = definitions[countryCode];
                if (definition) {
                    const candidates = { ...Object.fromEntries(Object.entries(definition.states).map(([code, name]) => [name as string, code])), ...definition.aliases };
                    const match = Object.entries(candidates).find(([name]) => normalize(name) === normalize(props.shapeName || ''));
                    if (match) return match[1] as string;
                }

                if (shapeIso && shapeIso !== 'None' && shapeIso !== countryCode) {
                    if (shapeIso.includes('-')) {
                        return shapeIso.split('-').slice(1).join('-').toUpperCase();
                    }
                    return shapeIso.toUpperCase();
                }

                const name = props.shapeName;
                if (name && name !== 'None') {
                    return name.normalize("NFD")
                        .replace(/[\u0300-\u036f]/g, "")
                        .replace(/[^a-zA-Z0-9]/g, "-")
                        .toUpperCase();
                }

                return 'UNKNOWN';
            };

            for (const countryRaw of countries) {
                const countryCode = countryRaw.toUpperCase();
                const countryDir = path.join(outDir, countryCode);
                fs.mkdirSync(countryDir, { recursive: true });

                console.log(`\n================================================`);
                console.log(`split-geojson: Iniciando processamento para: ${countryCode}`);
                console.log(`================================================`);

                manifest[countryCode] = { states: [] };

                // Resolve os caminhos oficiais de download via API
                const urlAdm0 = await getDownloadUrl(countryCode, 'ADM0');
                const urlAdm1 = await getDownloadUrl(countryCode, 'ADM1');
                const urlAdm2 = await getDownloadUrl(countryCode, 'ADM2');

                // Baixa e processa ADM0
                if (urlAdm0) {
                    const cacheFile = path.join(cacheDir, `${countryCode}-ADM0.geojson`);
                    const rawContent = await fetchAndCache(urlAdm0, cacheFile);

                    const destPath = path.join(countryDir, 'adm0.geojson');
                    fs.writeFileSync(destPath, rawContent);
                    console.log(`split-geojson: ADM0 salvo para ${countryCode}`);
                }

                // Baixa e processa ADM1
                let adm1Geojson: any = null;
                if (urlAdm1) {
                    const cacheFile = path.join(cacheDir, `${countryCode}-ADM1.geojson`);
                    const rawContent = await fetchAndCache(urlAdm1, cacheFile);
                    adm1Geojson = JSON.parse(rawContent);

                    const destPath = path.join(countryDir, 'adm1.geojson');
                    fs.writeFileSync(destPath, rawContent);
                    console.log(`split-geojson: ADM1 salvo para ${countryCode}`);
                }

                // Baixa, processa e fatia o ADM2
                if (urlAdm2) {
                    const cacheFile = path.join(cacheDir, `${countryCode}-ADM2.geojson`);
                    const rawContent = await fetchAndCache(urlAdm2, cacheFile);
                    const adm2Geojson = JSON.parse(rawContent);

                    // Executa o mapeamento espacial apenas se tivermos os estados (ADM1) carregados
                    if (adm1Geojson && adm1Geojson.features && adm2Geojson.features) {
                        const groupedFeatures: Record<string, any[]> = {};

                        console.log(`split-geojson: Mapeando espacialmente ${adm2Geojson.features.length} municípios contra os estados de ${countryCode}...`);

                        for (const municipality of adm2Geojson.features) {
                            if (!municipality.geometry) continue;

                            const mCentroid = centroid(municipality);
                            let assignedStateCode = 'UNKNOWN';

                            for (const stateFeature of adm1Geojson.features) {
                                if (!stateFeature.geometry) continue;

                                if (booleanPointInPolygon(mCentroid, stateFeature)) {
                                    const resolvedCode = cleanStateCode(stateFeature, countryCode);
                                    if (resolvedCode && resolvedCode !== 'UNKNOWN') {
                                        assignedStateCode = resolvedCode;
                                    }
                                    break;
                                }
                            }

                            if (assignedStateCode === 'UNKNOWN') continue;

                            if (!groupedFeatures[assignedStateCode]) {
                                groupedFeatures[assignedStateCode] = [];
                            }
                            groupedFeatures[assignedStateCode].push(municipality);
                        }

                        let count = 0;
                        for (const [stateCode, features] of Object.entries(groupedFeatures)) {
                            if (stateCode === 'UNKNOWN') continue;

                            const stateDir = path.join(countryDir, stateCode);
                            fs.mkdirSync(stateDir, { recursive: true });

                            const stateGeoJSON = {
                                type: 'FeatureCollection',
                                features: features
                            };

                            const destPath = path.join(stateDir, 'adm2.geojson');
                            fs.writeFileSync(destPath, JSON.stringify(stateGeoJSON));

                            manifest[countryCode].states.push(stateCode);
                            count++;
                        }

                        manifest[countryCode].states.sort();

                        console.log(`split-geojson: ADM2 fatiado em ${count} estados válidos para o país ${countryCode}`);
                    } else {
                        console.warn(`split-geojson: Ignorando o fatiamento de ADM2 de ${countryCode} pois o ADM1 correspondente não está disponível.`);
                    }
                }
            }

            // Gera o arquivo de manifesto na raiz da pasta de saída
            const manifestPath = path.join(outDir, 'manifest.json');
            fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
            await simplifyGeometryDirectory(path.resolve(outDir));
            buildLocationIndex(path.resolve(outDir), { metadataDirectory: path.resolve(cacheDir) });
            generateCountryClasses(path.resolve(outDir));
            console.log(validateRegionTranslations(path.resolve(outDir)).trim());
            console.log(`\n================================================`);
            console.log(`split-geojson: manifest.json gerado com sucesso!`);
            console.log(`================================================\n`);
        },
    };
}
