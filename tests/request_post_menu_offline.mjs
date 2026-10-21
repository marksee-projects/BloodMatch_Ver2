// Rendered menu and callback checks; no browser/network/database.
import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
const require=createRequire(new URL('../frontend/package.json',import.meta.url))
const {transformSync}=require('esbuild'), React=require('react'),{renderToStaticMarkup}=require('react-dom/server'),{MemoryRouter}=require('react-router-dom')
const jsLoader=require.extensions['.js']
require.extensions['.js']=(module,path)=>path.endsWith('.cjs.js')?module._compile(readFileSync(path,'utf8'),path):jsLoader(module,path)
require.extensions['.jsx']=(module,path)=>module._compile(transformSync("import React from 'react';\n"+readFileSync(path,'utf8'),{loader:'jsx',format:'cjs'}).code,path)
require.extensions['.css']=module=>{module.exports={root:'root',trigger:'trigger',menu:'menu',destructive:'destructive'}}
const Menu=require('./src/components/RequestPostMenu.jsx').default
const state=React.useState, ref=React.useRef
let open=false, tree, refs=[], props, called=[], inMenu=false
React.useState=initial=>{const actual=state(initial);if(!inMenu)return actual;return [open,value=>{open=typeof value==='function'?value(open):value}]}
let index=0
React.useRef=initial=>{const actual=ref(initial);if(!inMenu)return actual;return refs[index++] ||= actual}
function Fixture(){index=0;inMenu=true;try{tree=Menu(props);return tree}finally{inMenu=false}}
const render=status=>{props={request:{id:17,status},onEdit:()=>called.push('edit'),onCancel:()=>called.push('cancel'),onRenew:()=>called.push('renew')};return renderToStaticMarkup(React.createElement(MemoryRouter,null,React.createElement(Fixture)))}
const find=(node,predicate)=>Array.isArray(node)?node.map(child=>find(child,predicate)).find(Boolean):node&&typeof node==='object'?(predicate(node)?node:find(node.props?.children,predicate)):undefined
const consoleError=console.error;console.error=()=>{}
try {
  assert(!render('OPEN').includes('role="menu"'))
  find(tree,n=>n.props?.['aria-haspopup']==='menu').props.onClick();assert(open)
  let html=render('OPEN');assert(html.includes('Edit request')&&html.includes('Cancel request')&&!html.includes('Renew request'))
  find(tree,n=>n.props?.children==='Edit request').props.onClick();assert(!open);assert.deepEqual(called,['edit'])
  open=true;render('OPEN');find(tree,n=>n.props?.children==='Cancel request').props.onClick();assert.deepEqual(called,['edit','cancel'])
  open=true;html=render('EXPIRED');assert(html.includes('Renew request')&&!html.includes('Cancel request'))
  find(tree,n=>n.props?.children==='Renew request').props.onClick();assert.deepEqual(called,['edit','cancel','renew'])
  for(const status of ['FULFILLED','CANCELLED']){open=true;html=render(status);assert(html.includes('View details')&&!html.includes('Renew request')&&!html.includes('Edit request'))}
  open=true;render('OPEN');let focused=-1
  const items=[0,1].map(i=>({focus:()=>{focused=i}}))
  refs[0].current={querySelectorAll:()=>items};refs[1].current={focus:()=>{focused=2}}
  globalThis.document={activeElement:items[0]}
  tree.props.onKeyDown({key:'ArrowDown',preventDefault(){}});assert.equal(focused,1)
  tree.props.onKeyDown({key:'Home',preventDefault(){}});assert.equal(focused,0)
  tree.props.onKeyDown({key:'Escape',preventDefault(){},stopPropagation(){}});assert(!open);assert.equal(focused,2)
  tree.props.onBlur({currentTarget:{contains:()=>false},relatedTarget:{}});assert(!open)
  console.log('PASS menu visibility, status actions/callbacks, keyboard arrows/Home/Escape, focus restoration and focus-leave dismissal. SSR/callback checks, not mounted DOM.')
} finally {React.useState=state;React.useRef=ref;console.error=consoleError;delete globalThis.document}
