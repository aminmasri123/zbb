<script setup>
import { onMounted, ref, watch } from 'vue'

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
})

const emit = defineEmits(['update:modelValue', 'cleared'])

const canvas = ref(null)
let ctx = null
let drawing = false

const setupContext = () => {
  if (!canvas.value) return
  ctx = canvas.value.getContext('2d')
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.lineWidth = 3
  ctx.strokeStyle = '#111827'
}

const drawValue = (value) => {
  if (!ctx || !canvas.value || !value) return

  const image = new Image()
  image.onload = () => {
    ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
    ctx.drawImage(image, 0, 0, canvas.value.width, canvas.value.height)
  }
  image.src = value
}

const pointerPosition = (event) => {
  const rect = canvas.value.getBoundingClientRect()
  return {
    x: (event.clientX - rect.left) * (canvas.value.width / rect.width),
    y: (event.clientY - rect.top) * (canvas.value.height / rect.height),
  }
}

const startDrawing = (event) => {
  if (props.disabled || !ctx) return

  event.preventDefault()
  drawing = true
  canvas.value.setPointerCapture?.(event.pointerId)
  const point = pointerPosition(event)
  ctx.beginPath()
  ctx.moveTo(point.x, point.y)
}

const draw = (event) => {
  if (!drawing || props.disabled || !ctx) return

  event.preventDefault()
  const point = pointerPosition(event)
  ctx.lineTo(point.x, point.y)
  ctx.stroke()
}

const stopDrawing = (event) => {
  if (!drawing || !canvas.value) return

  event.preventDefault()
  drawing = false
  emit('update:modelValue', canvas.value.toDataURL('image/png'))
}

const clearSignature = () => {
  if (!ctx || !canvas.value) return

  ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
  emit('update:modelValue', '')
  emit('cleared')
}

watch(
  () => props.modelValue,
  (value) => {
    if (!ctx || !canvas.value) return

    if (!value) {
      ctx.clearRect(0, 0, canvas.value.width, canvas.value.height)
      return
    }

    drawValue(value)
  }
)

onMounted(() => {
  setupContext()
  drawValue(props.modelValue)
})
</script>

<template>
  <div class="flex items-center gap-2">
    <canvas
      ref="canvas"
      width="420"
      height="120"
      class="w-full touch-none rounded border border-gray-300 bg-white"
      :class="compact ? 'h-10 min-w-[92px]' : 'h-16'"
      @pointerdown="startDrawing"
      @pointermove="draw"
      @pointerup="stopDrawing"
      @pointerleave="stopDrawing"
      @pointercancel="stopDrawing"
    />

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
  </div>
</template>
