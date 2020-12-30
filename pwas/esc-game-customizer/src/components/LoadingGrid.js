import React, { PureComponent } from 'react';

import "./LoadingGrid.css";

export default class LoadingGrid extends PureComponent {
  render() {
    return (
      <div style={{
        position: 'relative',
        ...this.props.style,
      }}>
        <div className="lds-grid" style={{
          position: 'absolute',
          left: `calc(50% - 32px)`,
          top: `calc(50% - 32px)`,
        }}>
          <div></div>
          <div></div>
          <div></div>
          <div></div>
          <div></div>
          <div></div>
          <div></div>
          <div></div>
          <div></div>
        </div>
      </div>
    );
  }
}
