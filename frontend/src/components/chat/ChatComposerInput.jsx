import { useLayoutEffect, useRef } from 'react'

export default function ChatComposerInput({ value, onChange, inputRef, ...props }) {
  const localRef = useRef(null)
  const ref = inputRef || localRef
  useLayoutEffect(() => {
    const input = ref.current
    if (!input) return
    input.style.height = 'auto'
    input.style.height = `${Math.min(input.scrollHeight, 144)}px`
  }, [value, ref])
  return <textarea {...props} ref={ref} rows={1} wrap="soft" style={{ minHeight: 44, resize: 'none', overflowY: 'auto', overflowWrap: 'anywhere' }} value={value} onChange={onChange}
    onKeyDown={event => {
      if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) {
        event.preventDefault()
        event.currentTarget.form?.requestSubmit()
      }
    }} />
}
