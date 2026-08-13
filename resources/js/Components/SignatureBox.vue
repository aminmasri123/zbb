<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  compact: {
    type: Boolean,
    default: false,
  },
  participantName: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'cleared'])
const signatureInkColor = '#003f9e'

const canvas = ref(null)
const expandedCanvas = ref(null)
const expanded = ref(false)
const hasInk = ref(Boolean(props.modelValue))
let ctx = null
let expandedCtx = null
let expandedDrawing = false

const configureContext = (context, lineWidth = 4.5) => {
  if (!context) return

  context.lineCap = 'round'
  context.lineJoin = 'round'
  context.lineWidth = lineWidth
  context.strokeStyle = signatureInkColor
}

const setupContext = () => {
  if (!canvas.value) return
  ctx = canvas.value.getContext('2d')
  configureContext(ctx)
}

const setupExpandedContext = () => {
  if (!expandedCanvas.value) return
  expandedCtx = expandedCanvas.value.getContext('2d')
  configureContext(expandedCtx, 6)
}

const drawValueOnCanvas = (targetCanvas, targetCtx, value) => {
  if (!targetCtx || !targetCanvas) return

  targetCtx.clearRect(0, 0, targetCanvas.width, targetCanvas.height)
  if (!value) return

  const image = new Image()
  image.onload = () => {
    targetCtx.clearRect(0, 0, targetCanvas.width, targetCanvas.height)
    targetCtx.drawImage(image, 0, 0, targetCanvas.width, targetCanvas.height)
  }
  image.src = value
}

const syncCanvases = (value) => {
  drawValueOnCanvas(canvas.value, ctx, value)
  drawValueOnCanvas(expandedCanvas.value, expandedCtx, value)
}

const pointerPosition = (event, targetCanvas) => {
  const rect = targetCanvas.getBoundingClientRect()
  return {
    x: (event.clientX - rect.left) * (targetCanvas.width / rect.width),
    y: (event.clientY - rect.top) * (targetCanvas.height / rect.height),
  }
}

const openExpanded = async () => {
  if (props.disabled) return

  expanded.value = true
  await nextTick()
  setupExpandedContext()
  drawValueOnCanvas(expandedCanvas.value, expandedCtx, props.modelValue)
}

const closeExpanded = () => {
  expanded.value = false
  expandedDrawing = false
}

const startExpandedDrawing = (event) => {
  if (props.disabled || !expandedCtx || !expandedCanvas.value) return

  event.preventDefault()
  expandedDrawing = true
  expandedCanvas.value.setPointerCapture?.(event.pointerId)
  const point = pointerPosition(event, expandedCanvas.value)
  expandedCtx.beginPath()
  expandedCtx.moveTo(point.x, point.y)
}

const drawExpanded = (event) => {
  if (!expandedDrawing || props.disabled || !expandedCtx || !expandedCanvas.value) return

  event.preventDefault()
  const point = pointerPosition(event, expandedCanvas.value)
  expandedCtx.lineTo(point.x, point.y)
  expandedCtx.stroke()
}

const stopExpandedDrawing = (event) => {
  if (!expandedDrawing || !expandedCanvas.value) return

  event.preventDefault()
  expandedDrawing = false
  hasInk.value = true
  emit('update:modelValue', expandedCanvas.value.toDataURL('image/png'))
}

const clearSignature = async () => {
  if (props.disabled) return

  if (hasInk.value) {
    const participantText = props.participantName
      ? ` von ${props.participantName}`
      : ''
    const result = await Swal.fire({
      title: 'Unterschrift wirklich löschen?',
      text: `Die Unterschrift${participantText} kann nach dem Löschen nicht wiederhergestellt werden.`,
      icon: 'warning',
      input: 'text',
      inputLabel: 'Zur Bestätigung JA oder DELETE eingeben',
      inputPlaceholder: 'JA oder DELETE',
      showCancelButton: true,
      confirmButtonText: 'Unterschrift löschen',
      cancelButtonText: 'Abbrechen',
      customClass: {
        container: 'signature-swal-container',
      },
      preConfirm: (value) => {
        const confirmation = String(value || '').trim().toUpperCase()
        if (!['JA', 'DELETE', 'LÖSCHEN', 'LOESCHEN'].includes(confirmation)) {
          Swal.showValidationMessage('Bitte JA oder DELETE eingeben.')
          return false
        }
        return true
      },
    })

    if (!result.isConfirmed) return
  }

  syncCanvases('')
  hasInk.value = false
  emit('update:modelValue', '')
  emit('cleared')
}

watch(
  () => props.modelValue,
  (value) => {
    hasInk.value = Boolean(value)
    syncCanvases(value)
  }
)

onMounted(() => {
  setupContext()
  syncCanvases(props.modelValue)
})
</script>

<template>
  <div class="flex items-center gap-2">
    <canvas
      ref="canvas"
      width="420"
      height="120"
      class="w-full cursor-pointer rounded border border-gray-300 bg-white"
      :class="compact ? 'h-10 min-w-[92px]' : 'h-16'"
      aria-label="Vorschau der Unterschrift"
      title="Zum Unterschreiben oder Bearbeiten anklicken"
      @click="openExpanded"
    />

    <button
      type="button"
      class="inline-flex items-center justify-center rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
      :class="compact ? 'h-8 w-8' : 'h-9 w-9'"
      title="Unterschrift vergrößern"
      @pointerdown.stop
      @click="openExpanded"
    >
      <i class="la la-expand"></i>
    </button>

    <button
      type="button"
      class="inline-flex items-center justify-center rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
      :class="compact ? 'h-8 w-8' : 'h-9 w-9'"
      title="Unterschrift löschen"
      @pointerdown.stop
      @click="clearSignature"
    >
      <i class="la la-eraser"></i>
    </button>

    <Teleport to="body">
      <div
        v-if="expanded"
        class="fixed inset-0 z-[14000] flex items-center justify-center bg-black/60 p-2 sm:p-4"
        @click.self="closeExpanded"
      >
        <div class="flex h-[calc(100vh-1rem)] max-h-[900px] w-full max-w-[1500px] flex-col rounded border border-gray-200 bg-white p-3 shadow-2xl sm:h-[calc(100vh-2rem)] sm:p-5">
          <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="min-w-0 text-base font-bold text-gray-900 sm:text-lg">
              Unterschrift
              <span v-if="participantName" class="ml-1 font-normal text-gray-600">
                – {{ participantName }}
              </span>
            </h3>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
              title="Schließen"
              @click="closeExpanded"
            >
              <i class="la la-times"></i>
            </button>
          </div>

          <canvas
            ref="expandedCanvas"
            width="1400"
            height="600"
            class="min-h-0 w-full flex-1 touch-none rounded border-2 border-gray-400 bg-white"
            :aria-label="participantName ? `Unterschriftsfeld für ${participantName}` : 'Unterschriftsfeld'"
            @pointerdown="startExpandedDrawing"
            @pointermove="drawExpanded"
            @pointerup="stopExpandedDrawing"
            @pointerleave="stopExpandedDrawing"
            @pointercancel="stopExpandedDrawing"
          />

          <div class="mt-3 flex justify-end gap-2">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
              @click="clearSignature"
            >
              <i class="la la-eraser"></i>
              Löschen
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black"
              @click="closeExpanded"
            >
              Fertig
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
.signature-swal-container {
  z-index: 15000 !important;
}
</style>
