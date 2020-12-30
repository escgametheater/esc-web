import React from 'react';

import "./LoadingSpinner.css";

/**
 * https://loading.io/css/
 */
export default class LoadingSpinner extends React.PureComponent {
  render() {
    return (
      <React.Fragment>
        <div style={{...this.props.style || {}}} className="lds-ring"><div></div><div></div><div></div><div></div></div>
      </React.Fragment>
    );
  }
}
