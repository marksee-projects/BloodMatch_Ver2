import { useState, useRef, useEffect } from 'react'

export default function SearchableCombobox({ id, value, onChange, options, placeholder, required }) {
  const [isOpen, setIsOpen] = useState(false)
  const [inputValue, setInputValue] = useState(value || '')
  const wrapperRef = useRef(null)

  // Filter options based on input
  const filteredOptions = options.filter(opt => 
    opt.label.toLowerCase().includes(inputValue.toLowerCase())
  )

  // Handle outside clicks to close the dropdown
  useEffect(() => {
    function handleClickOutside(event) {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener("mousedown", handleClickOutside)
    return () => document.removeEventListener("mousedown", handleClickOutside)
  }, [wrapperRef])

  // Sync external value changes
  useEffect(() => {
    setInputValue(value || '')
  }, [value])

  const handleInputChange = (e) => {
    const val = e.target.value
    setInputValue(val)
    onChange(val)
    setIsOpen(true)
  }

  const handleSelectOption = (optValue) => {
    setInputValue(optValue)
    onChange(optValue)
    setIsOpen(false)
  }

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      if (isOpen && filteredOptions.length > 0) {
        handleSelectOption(filteredOptions[0].value)
      }
    }
  }

  // To match `.field input` perfectly, we just let it use the global styles by omitting inline font/padding that override it, 
  // but we can add inline styles just in case for the dropdown menu.
  return (
    <div ref={wrapperRef} style={{ position: 'relative', width: '100%' }}>
      <input
        id={id}
        type="text"
        value={inputValue}
        onChange={handleInputChange}
        onFocus={() => setIsOpen(true)}
        onKeyDown={handleKeyDown}
        placeholder={placeholder}
        autoComplete="off"
        required={required}
      />
      {isOpen && filteredOptions.length > 0 && (
        <ul style={{
          position: 'absolute',
          top: '100%',
          left: 0,
          right: 0,
          maxHeight: '220px',
          overflowY: 'auto',
          backgroundColor: 'var(--color-bg)',
          border: '1px solid var(--color-border)',
          borderRadius: 'var(--radius-md)',
          marginTop: '4px',
          padding: 0,
          margin: '4px 0 0 0',
          listStyle: 'none',
          zIndex: 50,
          boxShadow: '0 4px 12px rgba(0, 0, 0, 0.1)',
          boxSizing: 'border-box'
        }}>
          {filteredOptions.map((opt, i) => (
            <li 
              key={i}
              onClick={() => handleSelectOption(opt.value)}
              style={{ 
                padding: '10px 14px', 
                cursor: 'pointer', 
                borderBottom: i < filteredOptions.length - 1 ? '1px solid var(--color-border-hairline)' : 'none',
                fontFamily: 'var(--font-sans)',
                fontSize: '0.9375rem',
                color: 'var(--color-text)',
                transition: 'background 0.15s ease'
              }}
              onMouseOver={(e) => e.currentTarget.style.backgroundColor = 'var(--color-surface)'}
              onMouseOut={(e) => e.currentTarget.style.backgroundColor = 'transparent'}
            >
              {opt.label}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
