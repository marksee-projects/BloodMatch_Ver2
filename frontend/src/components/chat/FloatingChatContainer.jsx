import React from 'react'
import { useChat } from '../../context/ChatContext'
import FloatingChatTab from './FloatingChatTab'
import styles from './FloatingChatContainer.module.css'

export default function FloatingChatContainer() {
  const { openTabs, closeTab, toggleMinimizeTab, canChat } = useChat()

  if (!canChat || !openTabs || openTabs.length === 0) {
    return null
  }

  return (
    <div data-floating-chat={openTabs.some(tab => !tab.isMinimized) ? 'expanded' : 'minimized'} className={styles.container} role="region" aria-label="Active chat conversations">
      {openTabs.map((tab) => (
        <div key={tab.conversationId} className={styles.tabItem}>
          <FloatingChatTab
            tab={tab}
            onClose={() => closeTab(tab.conversationId)}
            onToggleMinimize={() => toggleMinimizeTab(tab.conversationId)}
          />
        </div>
      ))}
    </div>
  )
}
