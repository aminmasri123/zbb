<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    vehicle: Object,
    scanUrl: String,
    fahrtenbuchUrl: String,
});

const qrMatrix = computed(() => createQrMatrix(props.scanUrl));
const qrSize = computed(() => qrMatrix.value.length);
const darkModules = computed(() => {
    const modules = [];

    qrMatrix.value.forEach((row, y) => {
        row.forEach((isDark, x) => {
            if (isDark) {
                modules.push({ x, y });
            }
        });
    });

    return modules;
});

function printCode() {
    window.print();
}

function createQrMatrix(text) {
    const version = 6;
    const size = 17 + 4 * version;
    const dataCodewords = 136;
    const eccCodewordsPerBlock = 18;
    const blockCount = 2;
    const dataPerBlock = dataCodewords / blockCount;
    const bytes = Array.from(new TextEncoder().encode(text));

    if (bytes.length > 134) {
        throw new Error('Der Link ist zu lang für den QR-Code.');
    }

    const data = createDataCodewords(bytes, dataCodewords);
    const blocks = [];

    for (let i = 0; i < blockCount; i++) {
        const dataBlock = data.slice(i * dataPerBlock, (i + 1) * dataPerBlock);
        blocks.push({
            data: dataBlock,
            ecc: reedSolomonRemainder(dataBlock, eccCodewordsPerBlock),
        });
    }

    const codewords = [];

    for (let i = 0; i < dataPerBlock; i++) {
        blocks.forEach(block => codewords.push(block.data[i]));
    }

    for (let i = 0; i < eccCodewordsPerBlock; i++) {
        blocks.forEach(block => codewords.push(block.ecc[i]));
    }

    const modules = Array.from({ length: size }, () => Array(size).fill(false));
    const isFunction = Array.from({ length: size }, () => Array(size).fill(false));

    const setFunction = (x, y, isDark) => {
        if (x < 0 || y < 0 || x >= size || y >= size) {
            return;
        }

        modules[y][x] = isDark;
        isFunction[y][x] = true;
    };

    drawFinderPattern(setFunction, 0, 0);
    drawFinderPattern(setFunction, size - 7, 0);
    drawFinderPattern(setFunction, 0, size - 7);

    for (let i = 0; i < size; i++) {
        if (!isFunction[6][i]) {
            setFunction(i, 6, i % 2 === 0);
        }

        if (!isFunction[i][6]) {
            setFunction(6, i, i % 2 === 0);
        }
    }

    [6, 34].forEach(x => {
        [6, 34].forEach(y => {
            if (!isFunction[y][x]) {
                drawAlignmentPattern(setFunction, x, y);
            }
        });
    });

    setFunction(8, 4 * version + 9, true);
    drawFormatBits(setFunction, size, 0);
    drawCodewords(modules, isFunction, codewords);
    drawFormatBits(setFunction, size, getFormatBits(0));

    return modules;
}

function createDataCodewords(bytes, dataCodewords) {
    const bits = [];
    const pushBits = (value, length) => {
        for (let i = length - 1; i >= 0; i--) {
            bits.push((value >>> i) & 1);
        }
    };

    pushBits(0x4, 4);
    pushBits(bytes.length, 8);
    bytes.forEach(byte => pushBits(byte, 8));

    const capacity = dataCodewords * 8;
    const terminator = Math.min(4, capacity - bits.length);

    for (let i = 0; i < terminator; i++) {
        bits.push(0);
    }

    while (bits.length % 8 !== 0) {
        bits.push(0);
    }

    const codewords = [];

    for (let i = 0; i < bits.length; i += 8) {
        let value = 0;

        for (let j = 0; j < 8; j++) {
            value = (value << 1) | bits[i + j];
        }

        codewords.push(value);
    }

    let padIndex = 0;
    const pads = [0xec, 0x11];

    while (codewords.length < dataCodewords) {
        codewords.push(pads[padIndex % 2]);
        padIndex++;
    }

    return codewords;
}

function drawFinderPattern(setFunction, x, y) {
    for (let dy = -1; dy <= 7; dy++) {
        for (let dx = -1; dx <= 7; dx++) {
            const xx = x + dx;
            const yy = y + dy;
            const isCore = dx >= 0 && dx <= 6 && dy >= 0 && dy <= 6;
            const isDark = isCore && (
                dx === 0 || dx === 6 || dy === 0 || dy === 6 ||
                (dx >= 2 && dx <= 4 && dy >= 2 && dy <= 4)
            );

            setFunction(xx, yy, isDark);
        }
    }
}

function drawAlignmentPattern(setFunction, x, y) {
    for (let dy = -2; dy <= 2; dy++) {
        for (let dx = -2; dx <= 2; dx++) {
            setFunction(x + dx, y + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
    }
}

function drawFormatBits(setFunction, size, bits) {
    for (let i = 0; i <= 5; i++) {
        setFunction(8, i, getBit(bits, i));
    }

    setFunction(8, 7, getBit(bits, 6));
    setFunction(8, 8, getBit(bits, 7));
    setFunction(7, 8, getBit(bits, 8));

    for (let i = 9; i < 15; i++) {
        setFunction(14 - i, 8, getBit(bits, i));
    }

    for (let i = 0; i < 8; i++) {
        setFunction(size - 1 - i, 8, getBit(bits, i));
    }

    for (let i = 8; i < 15; i++) {
        setFunction(8, size - 15 + i, getBit(bits, i));
    }
}

function drawCodewords(modules, isFunction, codewords) {
    const bits = [];

    codewords.forEach(codeword => {
        for (let i = 7; i >= 0; i--) {
            bits.push((codeword >>> i) & 1);
        }
    });

    let bitIndex = 0;
    let upward = true;
    const size = modules.length;

    for (let right = size - 1; right >= 1; right -= 2) {
        if (right === 6) {
            right--;
        }

        for (let vertical = 0; vertical < size; vertical++) {
            const y = upward ? size - 1 - vertical : vertical;

            for (let offset = 0; offset < 2; offset++) {
                const x = right - offset;

                if (isFunction[y][x]) {
                    continue;
                }

                let isDark = bitIndex < bits.length ? bits[bitIndex] === 1 : false;
                bitIndex++;

                if ((x + y) % 2 === 0) {
                    isDark = !isDark;
                }

                modules[y][x] = isDark;
            }
        }

        upward = !upward;
    }
}

function getFormatBits(mask) {
    const eclLow = 1;
    const data = (eclLow << 3) | mask;
    let remainder = data << 10;

    for (let i = 14; i >= 10; i--) {
        if (((remainder >>> i) & 1) !== 0) {
            remainder ^= 0x537 << (i - 10);
        }
    }

    return ((data << 10) | (remainder & 0x3ff)) ^ 0x5412;
}

function getBit(value, index) {
    return ((value >>> index) & 1) !== 0;
}

function reedSolomonRemainder(data, degree) {
    const generator = reedSolomonGenerator(degree);
    const result = Array(degree).fill(0);

    data.forEach(byte => {
        const factor = byte ^ result.shift();
        result.push(0);

        for (let i = 0; i < degree; i++) {
            result[i] ^= gfMultiply(generator[i + 1], factor);
        }
    });

    return result;
}

function reedSolomonGenerator(degree) {
    let result = [1];

    for (let i = 0; i < degree; i++) {
        const next = Array(result.length + 1).fill(0);

        result.forEach((coefficient, index) => {
            next[index] ^= coefficient;
            next[index + 1] ^= gfMultiply(coefficient, gfPow(i));
        });

        result = next;
    }

    return result;
}

const gfTables = createGaloisTables();

function createGaloisTables() {
    const exp = Array(512).fill(0);
    const log = Array(256).fill(0);
    let value = 1;

    for (let i = 0; i < 255; i++) {
        exp[i] = value;
        log[value] = i;
        value <<= 1;

        if (value & 0x100) {
            value ^= 0x11d;
        }
    }

    for (let i = 255; i < 512; i++) {
        exp[i] = exp[i - 255];
    }

    return { exp, log };
}

function gfMultiply(x, y) {
    if (x === 0 || y === 0) {
        return 0;
    }

    return gfTables.exp[gfTables.log[x] + gfTables.log[y]];
}

function gfPow(power) {
    return gfTables.exp[power % 255];
}
</script>

<template>
    <Head :title="`Fahrtenbuch-Code ${vehicle.kennzeichen}`" />

    <AppLayout>
        <template #header>Fahrtenbuch-Code</template>

        <div class="no-print mb-6 flex flex-wrap gap-3">
            <button
                type="button"
                class="rounded bg-zbb px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
                @click="printCode"
            >
                Drucken
            </button>
            <Link :href="fahrtenbuchUrl" class="rounded border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                Fahrtenbuch öffnen
            </Link>
            <Link :href="route('dienstwagen.index')" class="rounded border px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                Zur Übersicht
            </Link>
        </div>

        <div class="print-area">
            <div class="label-sheet">
                <section class="vehicle-label">
                    <div>
                        <p class="eyebrow">Digitales Fahrtenbuch</p>
                        <h1>{{ vehicle.kennzeichen }}</h1>
                        <p class="vehicle-name">{{ vehicle.marke }} {{ vehicle.modell }}</p>
                        <p v-if="vehicle.standort" class="meta">{{ vehicle.standort.name }}</p>
                    </div>

                    <svg
                        class="qr-code"
                        :viewBox="`-4 -4 ${qrSize + 8} ${qrSize + 8}`"
                        role="img"
                        :aria-label="`QR-Code Fahrtenbuch ${vehicle.kennzeichen}`"
                    >
                        <rect :x="-4" :y="-4" :width="qrSize + 8" :height="qrSize + 8" fill="#ffffff" />
                        <rect
                            v-for="module in darkModules"
                            :key="`${module.x}-${module.y}`"
                            :x="module.x"
                            :y="module.y"
                            width="1"
                            height="1"
                            fill="#111827"
                        />
                    </svg>

                    <div class="instructions">
                        <strong>Scannen und Fahrt erfassen</strong>
                        <span>{{ scanUrl }}</span>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.label-sheet {
    display: flex;
    justify-content: center;
    padding: 2rem;
}

.vehicle-label {
    width: 95mm;
    min-height: 65mm;
    display: grid;
    grid-template-columns: 1fr 35mm;
    gap: 5mm;
    align-items: center;
    border: 1px solid #111827;
    border-radius: 4mm;
    background: #ffffff;
    color: #111827;
    padding: 6mm;
    font-family: Arial, sans-serif;
}

.vehicle-label .eyebrow {
    margin: 0 0 2mm;
    color: #4b5563;
    font-size: 9pt;
    font-weight: 700;
    text-transform: uppercase;
}

.vehicle-label h1 {
    margin: 0;
    font-size: 24pt;
    font-weight: 800;
    line-height: 1;
}

.vehicle-label .vehicle-name {
    margin: 2mm 0 0;
    font-size: 12pt;
    font-weight: 700;
}

.vehicle-label .meta {
    margin: 1mm 0 0;
    color: #4b5563;
    font-size: 10pt;
}

.vehicle-label .qr-code {
    width: 35mm;
    height: 35mm;
}

.vehicle-label .instructions {
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: 1mm;
    border-top: 1px solid #d1d5db;
    padding-top: 3mm;
    font-size: 9pt;
}

.vehicle-label .instructions span {
    color: #4b5563;
    overflow-wrap: anywhere;
}

@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    body * {
        visibility: hidden;
    }

    .print-area,
    .print-area * {
        visibility: visible;
    }

    .print-area {
        position: absolute;
        inset: 0;
    }

    .no-print {
        display: none !important;
    }

    .label-sheet {
        justify-content: flex-start;
        padding: 0;
    }
}
</style>
